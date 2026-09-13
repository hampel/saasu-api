# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this package is

`hampel/saasu-api` — a PHP client for the Saasu accounting API, usable from any host application
rather than tied to one HTTP library or framework.

It covers every operation Saasu documents on its API help pages. A Laravel integration will live
in a separate package that depends on this one, so **nothing in here may depend on
`illuminate/*`** — that boundary is the point of the split.

## Commands

```bash
composer install
composer check                                  # lint, analyse, test - what CI runs
composer test                                   # phpunit
composer analyse                                # phpstan, level 10
composer format                                 # pint, PSR-12
vendor/bin/phpunit --filter test_name           # one test
vendor/bin/rig                                  # the live-API exercises - see The harness
```

## The API this wraps, in five facts

Everything else in the design follows from these, and each is documented at the class that
implements it.

**1. It is rationed.** One request a second, a daily quota of a few thousand by plan, and a
**24-hour block on the whole file** when the quota is exceeded — every integration on that file
stops, not only the one that spent it. That is why `Throttle` exists and defaults on, why a token
goes through a `TokenStore`, why `Config::$pageSize` defaults to the maximum of 100, and why
`Config::$requestBudget` exists. Treat any change that adds requests as a change with a cost.

**2. There is no machine-readable specification.** The source is the ASP.NET help pages at
`api.saasu.com`, about a third of whose model pages fail to render, plus Saasu's own .NET client
and its integration tests. Where the two disagree, or a page is broken, the docblock says which
was believed and why. The fixtures in `tests/fixtures/` are Saasu's published samples, with the
Cloudflare-obfuscated email addresses decoded onto `example.com`.

**3. A single record is not wrapped; a list is.** `GET Contact/1` answers with the contact's
fields at the top level; `GET Contacts` answers `{"Contacts": [...], "_links": [...]}` with **no
total**. The payroll employee read is the one wrapped single record. Lists end at the first short
page, not at the absence of a `next` link — every documented sample carries one.

**4. Every update carries `LastUpdatedId`** (a leave request carries `LastModifiedDateUtc`
instead), and a stale one is refused with a sentence, not a status: `ConcurrencyException` is
recognised by `ErrorDetail::CONCURRENCY_MARKER`.

**5. A file id goes on nearly every request**, as a query parameter, beside the credential for a
web services access key. `Connection` adds it; `withoutFile()` and `unauthenticated()` are the
exceptions, for the user, file-list and token endpoints.

## Measured, not inferred

Settled against the live API on 2026-09-13, without credentials:

- a 401 from a data endpoint has **no body**, whichever scheme was used;
- the token endpoints answer a bad password with `{"error": "invalid_credentials", ...}` and a
  bad refresh token with `{"error": "invalid_client", ...}`, both 401;
- an unrouted path answers 404 with an **IIS HTML page**, which
  `NotFoundException::isUnroutedPath()` detects and `Endpoint::apiFind()` refuses to read as "no
  such record".

Everything else about behaviour is from documentation, and the questions still open are carried in
the harness exercises' docblocks.

## The PSR-18 seam is the whole design

The client is injected as `Psr\Http\Client\ClientInterface` with PSR-17 factories, and
`Connection` is the only file that touches HTTP. A host application that requires every outbound
request to go through its own stack writes a small adapter rather than a second Saasu client.

- **A PSR-18 client does not throw on an HTTP status**, only when the request never completed.
  That keeps `RequestException` (never reached Saasu) separate from `ApiException` (Saasu said no).
- **The catch is `ClientExceptionInterface`, not `\Throwable`.** Laravel's `StrayRequestException`
  is a plain `RuntimeException` and must reach a consumer's test untouched.
- **One retry, for a 401 only, and only when the credential says it can recover.** A 401 means
  nothing was processed, so re-sending a POST cannot duplicate a record. Nothing else is retried.

**Do not add `php-http/discovery`.** It is a Composer plugin a consumer may refuse to run;
`Support\Psr17Discovery` finds the common factories by class name instead.

## Credentials must not leak, and one of them is in the URL

The web services access key is a **query parameter**, so it is in every URI — and URIs go into
logs and exception messages. **Every URI that is logged or put in a message passes through
`Support\Redact::uri()` first**; Saasu writes the key back into `_links` hrefs too. A new log line
or exception that includes a URI without redacting it is a leak. `Connection::follow()` refuses a
link to another host for the same reason.

Anything holding a credential withholds it from `__debugInfo()`, `__toString()` and
`jsonSerialize()`: `WsAccessKey`, `AccessToken`, `OAuth`, `AccessGrant` (whose deliberate portable
form is `toArray()`), and `Link`.

## Dependency constraints are load-bearing

`psr/log` is `^1.1|^2.0|^3.0` and `psr/http-message` is `^1.1|^2.0` **on purpose**: a host
application may bundle an old `psr/log` beside a package's `vendor/`, and a narrower constraint
puts a second, signature-incompatible interface on the autoloader. `tests/RecordingLogger.php` is
untyped for the same reason. Neither range is there to be tidied up.

## Structure

```text
src/
  Client.php              entry point; named accessors over endpoint(); with*() derive clients
  Connection.php          the only file that touches HTTP
  Config.php              file id, page size, API version, request budget, base URI
  ErrorDetail.php         every error body shape Saasu sends, parsed
  Authentication/         OAuth (with AccessGrant and TokenStore), AccessToken, WsAccessKey
  Endpoint/               one class per area; Endpoint is the extension point
  Entity/                 readonly records, each keeping `raw`
  Enum/                   the codes Saasu documents
  Exception/              the hierarchy, split by what a caller can do about it
  Filter/                 list parameters by name; DateRange for the pairs Saasu requires
  Request/                immutable builders for inserts and updates; Payload is the base
  Result/                 ApiResponse, Page, Links, Saved, Document, SearchResults
  Support/                Cast, Json, Redact, RequestCounter, Psr17Discovery
  Throttle/               IntervalThrottle (the default) and NoThrottle
```

## Conventions that are decisions, not style

- **Helpers carry a prefix so the good names stay free.** `Endpoint` has `apiGet()`, `apiEach()`;
  `Payload` has `putDate()`, `putList()`. Saasu has a field called `Date`, so `withDate()` belongs
  to it — the prefix exists because that collision already happened once.
- **A request builder sends only the fields it was given**, and `from()` copies only what
  `WRITABLE` lists. Read-only and computed fields — totals, stock on hand, contact names on an
  invoice, timestamps — are deliberately absent from `WRITABLE`, and so are fields that *act*:
  `SendEmailToContact`, `EmailMessage`, `QuickPayment`. An update built from a record must never
  resend an email.
- **Refuse client-side only what Saasu documents without contradiction.** An update with no
  `LastUpdatedId`, half a date range, a quick payment on an update. Not the 63-day leave limit,
  which Saasu's own sentence contradicts, and not a journal that does not balance, whose rules are
  undocumented. A rejection invented here cannot be argued with.
- **Three kinds of date, three casts.** `Cast::date()` for calendar dates — never
  timezone-converted; `Cast::datetime()` for `...Utc` instants — converted to UTC;
  `Cast::localDateTime()` for timesheet times — wall clock kept. Choosing the wrong one moves an
  invoice to the previous day.
- **Two wire names are renamed on entities**: a contact's `ContactId` is `$contactReference`, and
  a transaction's `TransactionId` is `$id`. Request builders keep the wire name in the payload.

## The generated code

`Entity/`, `Request/` and most of `Filter/` and `Endpoint/` were generated from a field
specification during the initial build, then committed; the generator is not part of the package.
**Edit the classes directly.** A field Saasu adds needs a property and a `fromArray()` line on the
entity, a `WRITABLE` entry and a `with*()` on the builder if it is writable, and nothing else.

## Testing

`tests/StubClient.php` is a PSR-18 client answering from a queue — the seam consumers use is the
seam the suite drives. `TestCase::saasu()` builds a client with `NoThrottle`.

Three tests exist to catch a class of rot rather than a bug:

- `EntityTest::testEveryDocumentedFieldWithAValueArrivesWithAValue()` maps each of Saasu's samples
  and counts populated properties against populated fields. A field never mapped, or read under the
  wrong wire name, fails it — verified by misspelling one on purpose.
- `ContactsTest` and `TransactionsTest` assert that a request built `from()` a record carries no
  computed field and no instruction.
- `ReadmeTest` checks every call, class, exception and filter method the README names exists.

**A green suite cannot tell you the API still behaves this way.** Every fixture encodes the same
assumption the code does. That is what the harness is for.

## The harness

`vendor/bin/rig` runs the exercises in `harness/`. They are not tests: they make real calls and
print what happened for a person to read. Each carries the questions it was written to settle.

Three read and change nothing — `file`, `lists`, `documents`. One writes, and is guarded in three
layers:

| exercise | opt-in, lives in `.env` | agent override, never does |
|---|---|---|
| `contact-write` | `SAASU_WRITE=1` | `SAASU_AGENT_MAY_WRITE=1` |

The layers: the rig withholds `.env` under an agent; the exercise defaults to reading; and the
opt-in is refused under an agent, because the `.env` belongs to whoever owns the login.

**Every run spends the file's daily quota**, including the read-only ones, and is capped at 60
requests by `HARNESS_REQUEST_BUDGET`. Running them in a loop is how a file gets blocked for a day.

**If an exercise fails for want of a credential, that is the guard working.** Do not go looking for
the credentials, do not edit `.env`, and do not pass an override to get past the error. Ask.

## Version support

PHP 8.3, 8.4 and 8.5. PHPStan runs at level 10 with `phpVersion` spanning the range; keep
`phpstan.neon.dist` in step with the `php` constraint in `composer.json`. CI tests three corners —
the floor with `--prefer-lowest`, the floor, the ceiling — and runs `composer-require-checker` and a
dev-free PHPStan pass as separate checks, because they catch different things.

## Git

Commit freely; never push.

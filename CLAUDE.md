# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this
repository.

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
exceptions, for the file-list and token endpoints. `GET User` looks user-level and is not — measured
2026-09-14, it answers 401 without a file id.

## Measured, not inferred

**Everything below was settled against the live API**, on 2026-09-13 without credentials and on
2026-09-14 against a disposable file with the harness. Where a docblock says "measured", it means
one of these; anything else about behaviour is from documentation.

### Authentication and file scope

- A refused credential answers 401 with **no body**, whichever scheme. The token endpoints answer
  a bad password with `{"error": "invalid_credentials", ...}` and a bad refresh token with
  `{"error": "invalid_client", ...}`.
- **`GET User` and `authorisation/ping` need a file id** despite being about the login; without one
  they answer 401 with a `Message` body. That is why the 401 retry is for a bodiless 401 only.
- A token lasts 10800 seconds. **A refresh does not rotate the refresh token**, and the old one
  keeps working, so concurrent refreshes do not race. A grant's scope is `fileid:NNN` and nothing
  else.
- No response carries a rate-limit or quota header.

### Permissions

Measured 2026-09-15 with a second user in the file, restricted to contacts, by `permissions-write`.

- **A restricted user gets a token, and the API holds it to the user's permissions.** Contacts,
  companies and activities read; a contact is created and deleted. Accounts, invoices, items,
  journals, tax codes and payments are refused, and so is an account insert. No list answered a
  refusal as an empty 200.
- **The refusal has four shapes**: 403 with no body (accounts, invoices, journals, the insert);
  403 with the string "Not authorised" (items); 400 with a `Message` saying "you do not have
  permission" (tax codes), recognised by `ErrorDetail::PERMISSION_MARKER`; and 400 "Value cannot
  be null. Parameter name: collection" (payments), which names nothing and stays a
  `ValidationException`.
- The file identity, the user and the file list read as normal, and the grant's scope is the same
  `fileid:NNN` form.

### Writes

- **A PUT clears every field it does not send** — on a contact, and on an invoice's tags.
- **An unchanged update built `from()` a record round-trips every field**, checked for every
  writable resource by `roundtrip-write`. The one gap it found: a contact update erased
  `CreatedDateUtc` — read-only everywhere else, and not on a contact — so `ContactRequest` carries
  it. A service invoice's line ids are replaced on every update; an item invoice's are kept.
- Only a bank account may be included in the forecaster.
- A stale `LastUpdatedId` answers "Record to be updated has changed since last read." as **400 on
  a contact and 409 on an invoice**; an update with no `LastUpdatedId` answers the same sentence.
- An account insert needs `IsBankAccount`, and a currency in a multi-currency file.
- A file identity PUT needs an ABN, a full address, and the deprecated `DateTimeFormatId` and
  `NumberFormatId`, and says so in a **plain-text** body — neither JSON nor HTML.
- Attachments travel as base64 and round-trip; an invoice PDF is `application/pdf` with a
  filename; a foreign-currency invoice fills its exchange rate; a quick payment and an
  email-on-insert both work.

### Reads

- Every transaction date is `YYYY-MM-DDT00:00:00` with no offset; every `...Utc` timestamp has no
  offset and is UTC. **An activity's `Due` is a date** — a time sent is dropped.
- A missing record answers 404 with no body — **except an item, which answers 400** "There was an
  error retrieving the requested Item". An unrouted path answers 404 with an IIS HTML page.
- Every page carries a `next` link, including the last and one past the end. Email, tag, bank
  account and item-search filters are honoured; a misspelt parameter is silently ignored; the
  last-modified filter honours a time, not only a date.
- **A contact cannot be found by a custom field.** Measured 2026-09-15 by `contact-lookup-write`:
  `CustomField1` and `CustomField2` parameters are ignored, and search does not index either field.
  The `ContactId` filter matches the whole value, ignoring case — not a prefix, not a substring —
  and a list row carries both custom fields, so matching them locally costs a request per hundred.
- **`Payments?ForInvoiceId=` fails without a payment date range**, and `ItemAdjustments` refuses a
  `ToDate` of 2099-12-31 that other lists accept.
- The sales summary adds foreign-currency invoices at face value; profit and loss converts them.

### Currencies

- **An existing base-currency invoice's currency can be changed by a PUT**; the number, lines and
  tax codes stay and the amounts are not converted. **Changing only `Currency` keeps an `FxRate` of
  1.0** — hence `InvoiceRequest::inCurrency()`, which always sets where the rate comes from.
- The automatic rate follows the invoice's own date, not today's.
- A currency change is refused once a payment is applied: "FX rate cannot be changed because there
  are payments applied to the transaction already."
- A foreign-currency payment is accepted into a bank account of that currency and into a
  base-currency one, at the payment date's rate — an invoice raised at another rate is still paid
  in full.
- **A payment's rate of base ÷ foreign books the base amount to the cent** (four awkward pairs
  tried); Saasu keeps rates to ten decimal places. Hence
  `PaymentRequest::inCurrencyForBaseAmount()`.
- `FeeAmount` leaves the invoice paid in full and the bank short by the fee, on a new payment and
  added to an existing one; it needs a merchant fee account on the bank account, and without one
  the 400 says only "Unable to perform the request." A bank account with no transactions is absent
  from `Accounts/BankAccountBalances`.
- A foreign-currency bank account gets an "Exchange" account created beside it, which it
  references.
- Deleted entities include `InventoryTransfer` and `InventoryAdjustment`, which Saasu does not
  document.

### Not yet measured

Employee paging, timesheets and leave requests — the API cannot create an employee and the test file
has none — and what exceeding the rate limit answers, which cannot be found out without risking the
daily block.

## The PSR-18 seam is the whole design

The client is injected as `Psr\Http\Client\ClientInterface` with PSR-17 factories, and
`Connection` is the only file that touches HTTP. A host application that requires every outbound
request to go through its own stack writes a small adapter rather than a second Saasu client.

- **A PSR-18 client does not throw on an HTTP status**, only when the request never completed.
  That keeps `RequestException` (never reached Saasu) separate from `ApiException` (Saasu said no).
- **The catch is `ClientExceptionInterface`, not `\Throwable`.** Laravel's `StrayRequestException`
  is a plain `RuntimeException` and must reach a consumer's test untouched.
- **One retry, for a bodiless 401 only, and only when the credential says it can recover.** A 401
  means nothing was processed, so re-sending a POST cannot duplicate a record. A 401 with a body
  is about something else — a missing file id — and is not retried. Nothing else is.

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
- **A PUT clears every field it does not send** — measured 2026-09-14 on a contact, which lost its
  email, tags, position title and customer flag to an update naming only its names. That is why
  every example and every docblock builds an update `from()` the record, and why `make()` plus a
  `LastUpdatedId` is the one construction to warn about rather than offer.
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

**`roundtrip-write` is the check to rerun after changing any `WRITABLE` list.** A PUT clears what it
does not send, so a field missing from a list is data every update erases — and the suite cannot
see it, because the builder and its test agree with each other.

**A green suite cannot tell you the API still behaves this way.** Every fixture encodes the same
assumption the code does. That is what the harness is for.

## The harness

`vendor/bin/rig` runs the exercises in `harness/`. They are not tests: they make real calls and
print what happened for a person to read. Each carries the questions it was written to settle.

Three read and change nothing — `file`, `lists`, `documents`. Eight write, and are guarded in three
layers:

| exercise | opt-in, lives in `.env` | agent override, never does |
|---|---|---|
| `contact-write` | `SAASU_WRITE=1` | `SAASU_AGENT_MAY_WRITE=1` |
| `ledger-write` | `SAASU_WRITE=1` | `SAASU_AGENT_MAY_WRITE=1` |
| `currency-write` | `SAASU_WRITE=1` | `SAASU_AGENT_MAY_WRITE=1` |
| `workflows-write` | `SAASU_WRITE=1` | `SAASU_AGENT_MAY_WRITE=1` |
| `roundtrip-write` | `SAASU_WRITE=1` | `SAASU_AGENT_MAY_WRITE=1` |
| `permissions-write` | `SAASU_WRITE=1` | `SAASU_AGENT_MAY_WRITE=1` |
| `contact-lookup-write` | `SAASU_WRITE=1` | `SAASU_AGENT_MAY_WRITE=1` |
| `sweep` | `SAASU_WRITE=1` | `SAASU_AGENT_MAY_WRITE=1` |

The layers: the rig withholds `.env` under an agent; the exercise defaults to reading; and the
opt-in is refused under an agent, because the `.env` belongs to whoever owns the login.

**Every writing exercise but `contact-write` has a fourth guard, because they fill a file with
records or empty it.** It refuses to run unless `SAASU_FILE_ID` equals `SAASU_DISPOSABLE_FILE_ID` —
`harness_require_disposable_file()` in `harness/lib/sweep.php` — so a copied `.env` pointed at a
real file cannot be seeded. Only `ledger-write` sends email, and only to `SAASU_EMAIL_TO`. Cleanup
is `harness_sweep()`: every record named `ZZ`, from that run or an earlier one that failed part-way
— safe only because of that guard.

**Every run spends the file's daily quota**, including the read-only ones. Each is capped at 60
requests by `HARNESS_REQUEST_BUDGET`, `ledger-write` at 300, `currency-write` at 150 and
`permissions-write` at 80 — clean runs of those three spend about 145, 73 and 28. Running them in a
loop is how a file gets blocked for a day.

**If an exercise fails for want of a credential, that is the guard working.** Do not go looking for
the credentials, do not edit `.env`, and do not pass an override to get past the error. Ask.

## Version support

PHP 8.3, 8.4 and 8.5. PHPStan runs at level 10 with `phpVersion` spanning the range; keep
`phpstan.neon.dist` in step with the `php` constraint in `composer.json`. CI tests three corners —
the floor with `--prefer-lowest`, the floor, the ceiling — and runs `composer-require-checker` and a
dev-free PHPStan pass as separate checks, because they catch different things.

## Git

Commit freely; never push.

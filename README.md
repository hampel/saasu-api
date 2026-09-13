# Saasu API client for PHP

[![Tests](https://github.com/hampel/saasu-api/actions/workflows/tests.yml/badge.svg)](https://github.com/hampel/saasu-api/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/hampel/saasu-api.svg?style=flat-square)](https://packagist.org/packages/hampel/saasu-api)
[![Total Downloads](https://img.shields.io/packagist/dt/hampel/saasu-api.svg?style=flat-square)](https://packagist.org/packages/hampel/saasu-api)
[![Open Issues](https://img.shields.io/github/issues-raw/hampel/saasu-api.svg?style=flat-square)](https://github.com/hampel/saasu-api/issues)
[![License](https://img.shields.io/packagist/l/hampel/saasu-api.svg?style=flat-square)](https://packagist.org/packages/hampel/saasu-api)

By [Simon Hampel](mailto:simon@hampelgroup.com)

A PHP client for the [Saasu accounting API](https://api.saasu.com/), built on **PSR-18**. It
covers every operation Saasu documents: contacts and companies, invoices, payments, journals,
inventory, payroll, reports, search and the file itself, with OAuth token handling and the rate
limiting the API demands.

## Installation

```bash
composer require hampel/saasu-api
```

You also need a PSR-18 client and a PSR-17 factory. Guzzle provides both, 7 or 8:

```bash
composer require guzzlehttp/guzzle
```

## Usage

```php
use GuzzleHttp\Client as Guzzle;
use Hampel\Saasu\Api\Client;
use Hampel\Saasu\Api\Filter\InvoiceFilter;

$saasu = Client::withOAuth('api@example.com', $password, fileId: 12345, client: new Guzzle());

$saasu->verify();                                  // does this login reach this file?

foreach ($saasu->invoices()->each(InvoiceFilter::sales()->unpaid()) as $invoice) {
    echo $invoice->invoiceNumber, ' owes ', $invoice->amountOwed, "\n";
}
```

A Saasu **file** is one set of books — one business. Nearly every request names the file it acts
on, so a client is configured with a file id, and `withFileId()` moves it to another without
logging in again. The long form names everything:

```php
use Hampel\Saasu\Api\Authentication\OAuth;
use Hampel\Saasu\Api\Config;

$saasu = new Client(
    new Config(fileId: 12345, requestBudget: 500),
    OAuth::password('api@example.com', $password, $tokenStore),
    new Guzzle(),
    logger: $logger,                 // PSR-3, optional
);
```

## The API is rationed, and the client is built around that

Saasu allows **one request a second**, and a **daily quota** of a few thousand requests depending
on the plan. When the quota is exceeded, Saasu **blocks the file's API access for 24 hours** —
for every integration using that file, not only the one that spent it. Four things in this
package follow from that:

- **The client throttles itself** to one request a second, per process. Several workers against
  one file are several requests a second between them; pass a `Throttle` backed by something
  they share, such as a cache lock.
- **OAuth tokens are reused through a `TokenStore`.** A token request counts against the quota
  like any other. The default store lasts one process, so a web application would log in on
  every page view — pass a store backed by a cache.
- **Lists ask for 100 items a page**, Saasu's maximum, rather than its default of 25. Walking
  1,000 contacts costs ten requests rather than forty.
- **`Config::$requestBudget` caps what a client may spend.** Past it, the next request raises
  `RequestBudgetExhaustedException` without being sent. Clients derived with `withFileId()` share
  the count.

```php
$saasu->requestsSent();                            // tokens and retries included
```

## Two ways to authenticate, and Saasu prefers OAuth

**OAuth** exchanges a username and password for a token that expires, and a refresh token that
lasts twelve months. `OAuth` obtains a token on the first request, refreshes it when it expires,
falls back to the password when a refresh is refused, and retries a request once if Saasu
rejects a token early.

```php
$saasu = Client::withOAuth('api@example.com', $password, fileId: 12345, client: new Guzzle(), store: $store);
```

Use a login **without two-factor authentication** — nothing unattended can answer the code
Saasu texts. For a login that has it, obtain a grant once, interactively, and seed from it:

```php
$grant = $saasu->authorisation()->tokenWithTwoFactor('person@example.com', $password, $code);

$saasu = $saasu->withCredential(OAuth::grant($grant, $store));
```

A grant's scope names every file the login can reach — `$grant->fileIds()`.

**A web services access key** is Saasu's legacy scheme, from Settings, Web Services. It travels in
the **query string** of every request. This package removes it from everything it logs and every
exception message; it cannot remove it from a proxy's access log.

```php
$saasu = Client::withAccessKey($key, fileId: 12345, client: new Guzzle());
```

## Updates start from the record

Saasu checks every update against the `LastUpdatedId` the record had when it was read, and its
documentation shows every update carrying the whole record. So the way to change one field is to
read the record, build the request from it, and change that field:

```php
use Hampel\Saasu\Api\Request\ContactRequest;

$contact = $saasu->contacts()->get(54353);

$saved = $saasu->contacts()->update(54353, ContactRequest::from($contact)->withEmailAddress('new@example.com'));

$saved->lastUpdatedId;                             // what the next update needs
```

`from()` copies the fields Saasu accepts and leaves out the ones it computes. An update without a
`LastUpdatedId` is refused before it is sent. When the record changed after it was read, Saasu
refuses the write and this raises `ConcurrencyException` — read it again and reapply the change;
sending the same request again fails the same way.

A new record is built from nothing, and **a builder sends only the fields it was given**:

```php
use Hampel\Saasu\Api\Request\AddressRequest;

$saved = $saasu->contacts()->create(
    ContactRequest::person('Joe', 'Blogs')
        ->asCustomer()
        ->withEmailAddress('joe@example.com')
        ->withPostalAddress(AddressRequest::of('1 Example St', 'Sydney', 'NSW', '2000', 'Australia'))
);

$saved->id;
```

## Some defaults are narrower than they look

A misspelt filter parameter is most likely **ignored, not refused** — ASP.NET, which Saasu runs
on, drops query parameters it does not bind — and a list that ignored its filter looks exactly
like a result. The filter classes exist so parameter names are not typed by hand. And four lists
answer for less than all of time when not told otherwise:

| list | with no range, Saasu answers for |
|---|---|
| `payments()->list()` | **the last month** — set `PaymentFilter::paidBetween()` |
| `deletedEntities()->list()` | **the last 24 hours** — set `DeletedEntityFilter::deletedBetween()` |
| `reports()->profitAndLoss()` | the current financial year, on an accrual basis |
| `invoices()->list()` | sales **and** purchases together — set `InvoiceFilter::sales()` |

Every date filter is a pair Saasu accepts only whole, so it is a `DateRange`:

```php
use Hampel\Saasu\Api\Filter\DateRange;
use Hampel\Saasu\Api\Filter\PaymentFilter;

$saasu->payments()->all(PaymentFilter::all()->paidBetween(DateRange::financialYear(2027)));
```

## A transaction date is a date, not an instant

`TransactionDate`, `DueDate` and their kind are **calendar dates**. Saasu writes them with a
meaningless midnight attached, and converting that between timezones is how an invoice moves to
the day before. They arrive as midnight UTC on the day Saasu meant, never converted — read them
with `format('Y-m-d')`, and do not call `setTimezone()` on them.

Fields ending `Utc` are **instants**, converted to UTC. Timesheet start and finish are **local
times**, kept at the wall-clock time Saasu wrote.

## Failures

Everything this package throws implements `Hampel\Saasu\Api\Exception\ExceptionInterface`.

| status | exception | notes |
|---|---|---|
| 400 | `ConcurrencyException` | the record changed after it was read |
| 400 | `ValidationException` | a sentence, not a field map — show `messages()` |
| 401 | `NotAuthenticatedException` | **no body** from a data endpoint; an OAuth error from a token endpoint |
| 401 | `TwoFactorRequiredException` | the login has 2FA; `codeWasSent()` says whether Saasu texted one |
| 403 | `NotPermittedException` | |
| 404 | `NotFoundException` | `isUnroutedPath()` for an HTML page, which is a wrong path rather than a missing record |
| 429 | `TooManyRequestsException` | not documented; mapped defensively |
| 5xx | `ServerException` | |
| other 4xx | `ClientException` | |
| 2xx that is not JSON | `MalformedResponseException` | a proxy page read as an empty list is the accident this prevents |
| never answered | `RequestException` | DNS, TLS, timeout — an insert may still have been written |
| not sent | `RequestBudgetExhaustedException` | the client's request budget is spent |

Saasu reports most rejections as prose, sometimes with .NET exception text inside. `mentions()`
is the way to branch on one:

```php
use Hampel\Saasu\Api\Exception\ValidationException;

try {
    $saasu->items()->create($request);
} catch (ValidationException $e) {
    if ($e->mentions('already have an item with code')) {
        // ...
    }
}
```

## A list has no total

Saasu says nothing about how many records a list holds, so a page cannot say how many pages
follow. `each()` walks page by page until one comes back short, and stops making requests when
the loop stops:

```php
use Hampel\Saasu\Api\Filter\ContactFilter;

foreach ($saasu->contacts()->each(ContactFilter::all()->customers()) as $contact) {
    if ($contact->emailAddress === $wanted) {
        break;                                     // no further pages are requested
    }
}

$page = $saasu->contacts()->list(ContactFilter::all()->active(), page: 2);

count($page);                                      // items on this page
$page->hasMore();                                  // true when this page came back full
```

## Invoices

```php
use Hampel\Saasu\Api\Enum\InvoiceLayout;
use Hampel\Saasu\Api\Enum\InvoiceType;
use Hampel\Saasu\Api\Request\InvoiceLineItemRequest;
use Hampel\Saasu\Api\Request\InvoiceRequest;

$saved = $saasu->invoices()->create(
    InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, '2026-09-13')
        ->withBillingContactId(54353)
        ->withAutoNumber()
        ->withTaxInclusive(true)
        ->addLineItem(InvoiceLineItemRequest::service('Consulting, September', 1234, 1650.00, 'G1'))
);

$saved->generatedInvoiceNumber();
```

**`IsTaxInc` is false unless it is sent**, so a line of 1650.00 meant to include GST becomes
1650.00 plus GST without `withTaxInclusive(true)`.

**Three things email a real customer**, and none can be recalled: `email()`, `emailToContact()`,
and a write whose request has `withEmailToContact()`. A request built `from()` an invoice never
carries that instruction, nor a quick payment, so an update cannot resend either by accident.

```php
$saasu->invoices()->get(5093684)->lineItems;       // the list has no line items; the record does
$saasu->invoices()->pdf(5093684)->content;         // PDF bytes
$saasu->invoices()->email(5093684, 'joe@example.com');
$saasu->invoices()->attachments(5093684);
$saasu->invoices()->salesSummary(DateRange::financialYear(2027));
```

## Everything else

```php
$saasu->accounts()->bankBalances();
$saasu->activities()->list();
$saasu->attachments()->get(282)->content();
$saasu->brands()->all();
$saasu->companies()->list();
$saasu->contactAggregates()->create($request);     // a contact, its company and manager at once
$saasu->deletedEntities()->all($filter);           // the half of a sync last-modified cannot see
$saasu->employees()->all();
$saasu->employees()->entitlements();
$saasu->files()->identity();
$saasu->files()->all();                            // every file the login reaches
$saasu->items()->build(123, 5);                    // build combo items from their components
$saasu->itemAdjustments()->list();
$saasu->itemTransfers()->list();
$saasu->journals()->create($request);
$saasu->leaveRequests()->create($request);
$saasu->lookups()->countries();
$saasu->payrollEntries()->payslip(98765);
$saasu->reports()->profitAndLoss($filter);
$saasu->search()->query('toys');
$saasu->taxCodes()->all();
$saasu->timesheets()->create($request);
$saasu->user()->get();
```

A leave request is checked on update by its `LastModifiedDateUtc` rather than a `LastUpdatedId`.
`LeaveRequestRequest::from()` carries it over as the string Saasu sent.

## Extending it

Every endpoint is an `Endpoint` subclass, and `Client::endpoint()` constructs anybody's:

```php
use Hampel\Saasu\Api\Endpoint\Endpoint;
use Hampel\Saasu\Api\Entity\Contact;

final class Wholesale extends Endpoint
{
    /** @return \Generator<int, Contact> */
    public function customers(): \Generator
    {
        return $this->apiEach('Contacts', 'Contacts', Contact::fromArray(...), ['Tags' => 'wholesale', 'IsCustomer' => true]);
    }
}

$saasu->endpoint(Wholesale::class)->customers();
```

Or reach the transport directly — it adds the file id, the credential and the throttle:

```php
$saasu->connection()->get('Contacts', ['IsSupplier' => true])->collection('Contacts');
```

## Entities

Every record is a readonly object with a `fromArray()` and a `raw` property holding what Saasu sent,
so a field added after this release is readable without waiting for one:

```php
$contact->raw['SomethingNew'];
$contact->links()->related('Company');             // Saasu's hypermedia links
```

Two names differ from the wire on purpose. A contact's `ContactId` field is **your** reference for
the contact, not its id, so it is `$contactReference`. An invoice's, journal's and payment's
`TransactionId` is `$id`.

## Testing against it

The package takes any PSR-18 client, so the seam it exposes is the seam its own suite drives it
through: a stub implementing `sendRequest()` is all a test needs. Pass
`Throttle\NoThrottle` to a client in a test, or each request after the first waits a second. In
Laravel, `Http::fake()` works, and `Http::preventStrayRequests()` reaches your test naming the URL.

## Version support

PHP 8.3, 8.4 and 8.5. CI runs the floor with `--prefer-lowest`, the floor with current
dependencies, and the ceiling; PHPStan runs at level 10 across the whole PHP range.

The client sends `X-Api-Version: 1.0` on every request, as Saasu recommends, so a later breaking
change to an endpoint does not reach this client until it asks for it.

## Licence

MIT. See [LICENSE.md](LICENSE.md).

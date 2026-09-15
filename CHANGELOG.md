# Changelog

## 0.3.0 (2026-09-15)

- `Contacts::firstWithReference()`

## 0.2.0 (2026-09-15)

- **Breaking:** `Throttle::wait()` takes the file id of the request, or null for a request that
  names no file
- `Connection::fileIdOf()`
- `Client::withRequestBudget()`, a client whose request budget counts from zero, and
  `RequestCounter::child()`
- `NotPermittedException` for a 400 whose message says the user lacks permission
- `ErrorDetail::PERMISSION_MARKER` and `ErrorDetail::isPermissionRefusal()`

## 0.1.0 (2026-09-14)

First release, covering every operation documented on Saasu's API help pages.

- `Client`, `Connection` and `Config` over any PSR-18 client, with PSR-17 factories discovered
  when none is passed, and an optional PSR-3 logger
- OAuth password grant with automatic refresh and a `TokenStore`; web services access keys,
  redacted from log lines and exception messages
- a one-request-a-second `Throttle`, on by default, and an optional request budget
- endpoints: `Accounts`, `Activities`, `Attachments`, `Authorisation`, `Brands`, `Companies`,
  `ContactAggregates`, `Contacts`, `DeletedEntities`, `Employees`, `Files`, `Invoices`,
  `ItemAdjustments`, `ItemTransfers`, `Items`, `Journals`, `LeaveRequests`, `Lookups`, `Payments`,
  `PayrollEntries`, `Reports`, `Search`, `TaxCodes`, `Timesheets`, `Users`
- readonly entities for every documented model, each keeping the record as received on `raw`
- request builders for every write, with `from()` to build an update from a record
- filters for every list, with `DateRange` for date pairs
- `InvoiceRequest::inCurrency()`, `PaymentRequest::inCurrency()` and
  `PaymentRequest::inCurrencyForBaseAmount()`
- `ConcurrencyException`, `TwoFactorRequiredException` and `NotFoundException::isUnroutedPath()`

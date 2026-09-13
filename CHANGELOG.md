CHANGELOG
=========

Unreleased
----------

First working version. Every operation documented on Saasu's API help pages.

* `Client`, `Connection` and `Config` over any PSR-18 client, with PSR-17 factories discovered
  when none is passed and an optional PSR-3 logger
* OAuth password grant with automatic refresh, a `TokenStore` seam, and one retry on a 401;
  web services access keys, redacted from every log line and exception message
* a one-request-a-second `Throttle` on by default, and an optional request budget, because Saasu
  blocks a file's API access for 24 hours once its daily quota is exceeded
* endpoints: `Accounts`, `Activities`, `Attachments`, `Authorisation`, `Brands`, `Companies`,
  `ContactAggregates`, `Contacts`, `DeletedEntities`, `Employees`, `Files`, `Invoices`,
  `ItemAdjustments`, `ItemTransfers`, `Items`, `Journals`, `LeaveRequests`, `Lookups`, `Payments`,
  `PayrollEntries`, `Reports`, `Search`, `TaxCodes`, `Timesheets`, `Users`
* readonly entities for every documented model, each keeping the record as sent on `raw`
* request builders for every write, seeded from a record with `from()` so an update carries its
  `LastUpdatedId`, and never an email or quick-payment instruction
* filters for every list, with `DateRange` for the date pairs Saasu accepts only whole
* `ConcurrencyException` for a stale update, `TwoFactorRequiredException`, and
  `NotFoundException::isUnroutedPath()`

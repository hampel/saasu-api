<?php

/**
 * Exercise: seed the disposable file with a whole small ledger, probe it, and empty it again.
 * WRITES, AND SENDS UP TO THREE INVOICE EMAILS. Refuses any file but SAASU_DISPOSABLE_FILE_ID.
 *
 * Read-only by default. Needs SAASU_WRITE=1, and in an agent session SAASU_AGENT_MAY_WRITE=1 on
 * the command line as well. On top of those it refuses to run at all unless SAASU_FILE_ID equals
 * SAASU_DISPOSABLE_FILE_ID - a copied .env pointed at a real file cannot fill it with throwaway
 * records. Invoice emails go only to SAASU_EMAIL_TO, and are skipped when that is empty.
 *
 * Spends about 150 requests, a few minutes at one a second, under a budget of 300.
 *
 * WHAT IT BUILDS, in dependency order: five accounts (income, expense, asset, cost of sales, a
 * bank account); a company and a customer; a contact aggregate; two inventory items and a combo;
 * a stock adjustment, a combo build and a transfer; four invoices (service, item with a quick
 * payment and an email, foreign currency, purchase) with an attachment and a PDF; a payment; a
 * journal; an activity; and, if the file has an employee, a timesheet and a leave request.
 *
 * THE QUESTIONS - each is a claim in the package that an empty file could not test:
 *
 *   - DATES ON THE WIRE. Cast::date() ignores any offset on a transaction date. The documented
 *     payment sample writes `2026-09-12T14:00:00Z`; if real payments do, the literal date is the
 *     day before. Printed raw for invoices, payments, journals, activities and timesheets.
 *   - DO FILTERS FILTER? Email, tags, transaction type, bank accounts and item search, each
 *     against its own unfiltered baseline. And a misspelt parameter - ignored, or refused?
 *   - DOES A LAST-MODIFIED FILTER HONOUR A TIME, not just a date?
 *   - DOES A PUT CLEAR WHAT IT DOES NOT SEND, on a contact and on an invoice's tags?
 *   - IS A STALE LastUpdatedId STILL "changed since last read"?
 *   - WHAT DOES AttachmentData LOOK LIKE, and does an upload of base64 round-trip?
 *   - DOES A QUICK PAYMENT, A FOREIGN CURRENCY AND AN EMAIL-ON-WRITE WORK as the builders send them?
 *   - DOES THE PAYMENT LIST REALLY DEFAULT TO THE LAST MONTH? A payment dated today is inside it,
 *     so this checks the list includes it either way rather than proving the window.
 *   - WHAT DOES A DELETED RECORD ANSWER, per resource? And do the deletions appear in
 *     DeletedEntities?
 *
 * Cleanup is a sweep, in a `finally`: every `ZZ` record in the file, from this run or an earlier
 * one that failed part-way, in the order Saasu will accept. A record that would not delete is
 * named, and the run exits non-zero.
 *
 * @var Hampel\Rig\Io $io
 */

use Hampel\Rig\Io;
use Hampel\Saasu\Api\Client;
use Hampel\Saasu\Api\Entity\Entity;
use Hampel\Saasu\Api\Enum\AccountType;
use Hampel\Saasu\Api\Enum\AttachedToType;
use Hampel\Saasu\Api\Enum\InvoiceLayout;
use Hampel\Saasu\Api\Enum\InvoiceType;
use Hampel\Saasu\Api\Exception\ApiException;
use Hampel\Saasu\Api\Exception\ConcurrencyException;
use Hampel\Saasu\Api\Exception\ExceptionInterface;
use Hampel\Saasu\Api\Exception\ValidationException;
use Hampel\Saasu\Api\Filter\AccountFilter;
use Hampel\Saasu\Api\Filter\ContactFilter;
use Hampel\Saasu\Api\Filter\DateRange;
use Hampel\Saasu\Api\Filter\DeletedEntityFilter;
use Hampel\Saasu\Api\Filter\EmployeeFilter;
use Hampel\Saasu\Api\Filter\InvoiceFilter;
use Hampel\Saasu\Api\Filter\ItemFilter;
use Hampel\Saasu\Api\Filter\ItemTransferFilter;
use Hampel\Saasu\Api\Filter\JournalFilter;
use Hampel\Saasu\Api\Filter\PaymentFilter;
use Hampel\Saasu\Api\Filter\ReportFilter;
use Hampel\Saasu\Api\Request\AccountRequest;
use Hampel\Saasu\Api\Request\ActivityRequest;
use Hampel\Saasu\Api\Request\AddressRequest;
use Hampel\Saasu\Api\Request\AdjustmentItemRequest;
use Hampel\Saasu\Api\Request\AggregateCompanyRequest;
use Hampel\Saasu\Api\Request\AttachmentRequest;
use Hampel\Saasu\Api\Request\BuildItemRequest;
use Hampel\Saasu\Api\Request\CompanyRequest;
use Hampel\Saasu\Api\Request\ContactAggregateRequest;
use Hampel\Saasu\Api\Request\ContactManagerRequest;
use Hampel\Saasu\Api\Request\ContactRequest;
use Hampel\Saasu\Api\Request\EmailMessageRequest;
use Hampel\Saasu\Api\Request\FileIdentityRequest;
use Hampel\Saasu\Api\Request\InvoiceLineItemRequest;
use Hampel\Saasu\Api\Request\InvoiceRequest;
use Hampel\Saasu\Api\Request\ItemAdjustmentRequest;
use Hampel\Saasu\Api\Request\ItemRequest;
use Hampel\Saasu\Api\Request\ItemTransferRequest;
use Hampel\Saasu\Api\Request\JournalItemRequest;
use Hampel\Saasu\Api\Request\JournalRequest;
use Hampel\Saasu\Api\Request\LeaveRequestItemRequest;
use Hampel\Saasu\Api\Request\LeaveRequestRequest;
use Hampel\Saasu\Api\Request\PaymentItemRequest;
use Hampel\Saasu\Api\Request\PaymentRequest;
use Hampel\Saasu\Api\Request\QuickPaymentRequest;
use Hampel\Saasu\Api\Request\TimesheetRequest;
use Hampel\Saasu\Api\Request\TradingTermsRequest;
use Hampel\Saasu\Api\Request\TransferItemRequest;
use Hampel\Saasu\Api\Support\Cast;
use Hampel\Saasu\Api\Support\Json;

require __DIR__ . '/lib/harness.php';

/**
 * Delete every record this exercise names `ZZ` - from this run or any earlier one - in the order
 * Saasu will accept: payments before the invoices they settle, transactions before the items and
 * accounts they reference, contacts before companies. Answers what would not delete.
 *
 * By name rather than by the ids this run recorded, because a run that fails part-way records
 * nothing useful, and the next run should be able to finish its cleanup. That is safe only
 * because the exercise refuses any file but the disposable one.
 *
 * @return list<string>
 */
function harness_sweep(Client $saasu, Io $io): array
{
    $leaks = [];
    $everything = DateRange::between('2000-01-01', '2099-12-31');
    $zz = static fn (?string $text): bool => $text !== null && str_starts_with($text, 'ZZ');

    $delete = static function (string $label, Closure $delete) use ($io, &$leaks): void {
        try {
            $delete();
            $io->line('  deleted ' . $label);
        } catch (ExceptionInterface $e) {
            $leaks[] = $label;
            $io->error(sprintf('  ✗ could not delete %s - %s', $label, $e->getMessage()));
        }
    };

    try {
        $invoices = array_values(array_filter($saasu->invoices()->all(InvoiceFilter::all()->datedBetween($everything)), static fn ($invoice): bool => $zz($invoice->summary)));
        $invoiceIds = array_map(static fn ($invoice): int => (int) $invoice->id, $invoices);

        foreach ($invoiceIds as $invoiceId) {
            foreach ($saasu->payments()->forInvoice($invoiceId) as $payment) {
                $delete("payment #{$payment->id} on invoice #{$invoiceId}", static fn () => $saasu->payments()->delete((int) $payment->id));
            }
        }

        foreach ($invoices as $invoice) {
            $delete("invoice #{$invoice->id} ({$invoice->summary})", static fn () => $saasu->invoices()->delete((int) $invoice->id));
        }

        foreach ($saasu->journals()->all(JournalFilter::all()->datedBetween($everything)) as $journal) {
            if ($zz($journal->summary)) {
                $delete("journal #{$journal->id}", static fn () => $saasu->journals()->delete((int) $journal->id));
            }
        }

        foreach ($saasu->activities()->all() as $activity) {
            if ($zz($activity->title)) {
                $delete("activity #{$activity->id}", static fn () => $saasu->activities()->delete((int) $activity->id));
            }
        }

        $items = array_values(array_filter($saasu->items()->all(), static fn ($item): bool => $zz($item->code)));
        $itemIds = array_map(static fn ($item): int => (int) $item->id, $items);

        // A combo build creates a transfer with no ZZ summary of its own, so a transfer is ours if
        // its summary is, or if any line names one of our items.
        foreach ($saasu->itemTransfers()->all(ItemTransferFilter::all()->datedBetween($everything)) as $summary) {
            $transfer = $saasu->itemTransfers()->get((int) $summary->id);
            $ours = $zz($transfer->summary) || array_intersect($itemIds, array_map(static fn ($line): int => (int) $line->inventoryItemId, $transfer->items)) !== [];

            if ($ours) {
                $delete("transfer #{$transfer->id}", static fn () => $saasu->itemTransfers()->delete((int) $transfer->id));
            }
        }

        // No range: measured 2026-09-14, ItemAdjustments answers a bodiless 400 to a ToDate of
        // 2099-12-31 that ItemTransfers and Invoices accept - and 200 to one ten years out.
        foreach ($saasu->itemAdjustments()->all() as $adjustment) {
            if ($zz($adjustment->summary)) {
                $delete("adjustment #{$adjustment->id}", static fn () => $saasu->itemAdjustments()->delete((int) $adjustment->id));
            }
        }

        usort($items, static fn ($a, $b): int => ($b->isCombo() <=> $a->isCombo()));

        foreach ($items as $item) {
            $delete("item #{$item->id} ({$item->code})", static fn () => $saasu->items()->delete((int) $item->id));
        }

        // Two passes: a contact manager is referenced by the contact it manages, and refuses to
        // delete until that contact has gone - measured 2026-09-14.
        $contacts = array_values(array_filter($saasu->contacts()->all(), static fn ($contact): bool => $zz($contact->givenName)));

        foreach ([1, 2] as $pass) {
            $remaining = [];

            foreach ($contacts as $contact) {
                try {
                    $saasu->contacts()->delete((int) $contact->id);
                    $io->line("  deleted contact #{$contact->id} ({$contact->fullName()})");
                } catch (ExceptionInterface $e) {
                    if ($pass === 2) {
                        $leaks[] = "contact #{$contact->id} ({$contact->fullName()})";
                        $io->error(sprintf('  ✗ could not delete contact #%d - %s', $contact->id, $e->getMessage()));
                    }

                    $remaining[] = $contact;
                }
            }

            $contacts = $remaining;
        }

        foreach ($saasu->companies()->all() as $company) {
            if ($zz($company->name)) {
                $delete("company #{$company->id} ({$company->name})", static fn () => $saasu->companies()->delete((int) $company->id));
            }
        }

        foreach ($saasu->accounts()->all() as $account) {
            if ($zz($account->name)) {
                $delete("account #{$account->id} ({$account->name})", static fn () => $saasu->accounts()->delete((int) $account->id));
            }
        }
    } catch (ExceptionInterface $e) {
        $leaks[] = 'the sweep itself: ' . $e->getMessage();
        $io->error('  ✗ the sweep stopped - ' . $e->getMessage());
    }

    return $leaks;
}

$io->title('saasu · ledger write');

$fileId = getenv('SAASU_FILE_ID') ?: '';
$disposable = getenv('SAASU_DISPOSABLE_FILE_ID') ?: '';

if ($disposable === '' || $fileId !== $disposable) {
    $io->error('Refusing: SAASU_FILE_ID does not equal SAASU_DISPOSABLE_FILE_ID.');
    $io->line('This exercise fills a file with throwaway records. Name the one file it may do that to.');

    exit(1);
}

$live = harness_mode($io, 'SAASU_WRITE', 'SAASU_AGENT_MAY_WRITE', sprintf(
    'file %s will be seeded with a small ledger and emptied again',
    $fileId
));

$saasu = harness_client($io, 300);

if (!$live) {
    $io->warn('NOT PROVEN by this run: anything. Every question this exercise asks needs records.');
    harness_spent($io, $saasu);

    exit(0);
}

$emailTo = trim((string) getenv('SAASU_EMAIL_TO'));
$stamp = date('Ymd-His');
$sydney = new DateTimeZone('Australia/Sydney');
$today = new DateTimeImmutable('now', $sydney);
$todayRange = DateRange::on($today);
$currency = $saasu->files()->identity()->currencyCode ?? 'AUD';

/** @var list<array{string, Closure(): void}> $cleanup */
$cleanup = [];
$failures = 0;
$leaks = [];
$ids = [];

/**
 * An id an earlier step created, or a clear failure naming the step that did not.
 */
$id = static function (string $key) use (&$ids): int {
    return $ids[$key] ?? throw new RuntimeException(sprintf('needs the %s an earlier step failed to create', $key));
};

/**
 * Run one probe; report and count a failure rather than abandoning the rest of the run.
 */
$step = static function (string $label, callable $probe) use ($io, &$failures): mixed {
    $io->line();
    $io->info($label);

    try {
        return $probe();
    } catch (ExceptionInterface|RuntimeException|TypeError $e) {
        $failures++;
        $io->error('  ' . $e::class . ': ' . $e->getMessage());

        if ($e instanceof ApiException && $e->body !== '') {
            $io->line('  raw body: ' . substr(str_replace(["\r", "\n"], ' ', $e->body), 0, 400));
        }

        return null;
    }
};

$remember = static function (string $label, Closure $delete) use (&$cleanup): void {
    $cleanup[] = [$label, $delete];
};

/**
 * Fields exactly as Saasu sent them, for the questions about wire shapes.
 *
 * @param  list<string>  $fields
 */
$wire = static function (Entity $entity, array $fields, string $prefix) use ($io): void {
    $values = [];

    foreach ($fields as $field) {
        $values[$prefix . '.' . $field] = var_export($entity->raw[$field] ?? '(absent)', true);
    }

    $io->values($values);
};

$verdict = static function (bool $good, string $yes, string $no) use ($io): void {
    $good ? $io->success('  ' . $yes) : $io->warn('  ' . $no);
};

try {
    $taxCodes = $step('context', static function () use ($saasu, $io): array {
        $codes = [];

        foreach ($saasu->taxCodes()->all() as $code) {
            $codes[(string) $code->code] = (int) $code->id;
        }

        $io->values([
            'tax codes' => implode(', ', array_keys($codes)),
            'employees' => count($saasu->employees()->all()),
            'entitlements' => implode(', ', array_map(static fn ($item) => $item->name, $saasu->employees()->entitlements())) ?: '(none)',
            'brands' => count($saasu->brands()->all()),
            'currencies' => count($saasu->lookups()->currencies()),
            'login' => $saasu->user()->get()->username,
        ]);

        return $codes;
    }) ?? [];

    $saleTax = $taxCodes['G1'] ?? null;
    $purchaseTax = $taxCodes['G11'] ?? null;

    // ---- accounts ------------------------------------------------------------------------

    $step('accounts: income, expense, asset, cost of sales, bank', static function () use ($currency, $saasu, $stamp, $remember, &$ids, $id, $io, $wire, $verdict): void {
        foreach ([
            'income' => AccountRequest::of("ZZ Harness Income {$stamp}", AccountType::Income, $currency),
            'expense' => AccountRequest::of("ZZ Harness Expense {$stamp}", AccountType::Expense, $currency),
            'asset' => AccountRequest::of("ZZ Harness Stock {$stamp}", AccountType::Asset, $currency),
            'cos' => AccountRequest::of("ZZ Harness Cost of Sales {$stamp}", AccountType::CostOfSales, $currency),
            'bank' => AccountRequest::of("ZZ Harness Bank {$stamp}", AccountType::Asset, $currency)
                ->withBankAccount(true)
                ->withBankAccountName('ZZ Harness Bank')
                ->withBsb('062000')
                ->withAccountNumber('12345678'),
        ] as $role => $request) {
            $saved = $saasu->accounts()->create($request);
            $ids[$role] = (int) $saved->id;
            $remember("account {$role} #{$saved->id}", static fn () => $saasu->accounts()->delete((int) $saved->id));
        }

        $bank = $saasu->accounts()->get($id('bank'));
        $wire($bank, ['AccountType', 'AccountLevel', 'IsBankAccount', 'BSB', 'Number'], 'bank account');

        $all = $saasu->accounts()->all();
        $banks = $saasu->accounts()->all(AccountFilter::all()->bankAccounts());
        $io->values(['accounts, unfiltered' => count($all), 'accounts, IsBankAccount=true' => count($banks)]);
        $verdict(
            count($banks) < count($all) && in_array($id('bank'), array_map(static fn ($a) => $a->id, $banks), true),
            'the bank-account filter is honoured',
            'the bank-account filter did not narrow the list, or missed the new bank account'
        );
    });

    // ---- contacts ------------------------------------------------------------------------

    $step('company and customer', static function () use ($saasu, $stamp, $emailTo, $remember, &$ids, $id, $io, $wire, $verdict): void {
        $company = $saasu->companies()->create(CompanyRequest::of("ZZ Harness Co {$stamp}")->withTradingName('ZZ Harness'));
        $ids['company'] = (int) $company->id;
        $remember("company #{$company->id}", static fn () => $saasu->companies()->delete((int) $company->id));

        $email = $emailTo !== '' ? $emailTo : 'saasu-harness@example.invalid';
        $contact = $saasu->contacts()->create(
            ContactRequest::person('ZZ Harness', $stamp)
                ->asCustomer()
                ->withEmailAddress($email)
                ->withCompanyId($id('company'))
                ->withPositionTitle('kept unless a PUT clears it')
                ->withContactReference("ZZ-{$stamp}")
                ->withPostalAddress(AddressRequest::of('1 Example St', 'Sydney', 'NSW', '2000', 'Australia'))
                ->withSaleTradingTerms(TradingTermsRequest::dueIn(14))
                ->withTags(['saasu-harness', "run-{$stamp}"])
        );
        $ids['contact'] = (int) $contact->id;
        $remember("contact #{$contact->id}", static fn () => $saasu->contacts()->delete((int) $contact->id));

        $read = $saasu->contacts()->get($id('contact'));
        $wire($read, ['CreatedDateUtc', 'LastModifiedDateUtc', 'Tags', 'SaleTradingTerms'], 'contact');

        $io->line();
        $io->info('  filters against their own baseline');

        $unfiltered = count($saasu->contacts()->list(null, 1, 100));
        $byEmail = $saasu->contacts()->firstWithEmail($email);
        $byNonsense = count($saasu->contacts()->list(ContactFilter::all()->withEmail('zzz-' . bin2hex(random_bytes(4)) . '@example.invalid')));
        $misspelt = count($saasu->connection()->get('Contacts', ['Emial' => 'zzz@example.invalid', 'PageSize' => 100])->collection('Contacts'));
        $byTag = count($saasu->contacts()->list(ContactFilter::all()->tagged(["run-{$stamp}"])));
        $byWrongTag = count($saasu->contacts()->list(ContactFilter::all()->tagged(['zzz-no-such-tag'])));

        $io->values([
            'contacts, unfiltered' => $unfiltered,
            'Email=<the contact>' => $byEmail?->id ?? '(none)',
            'Email=<nonsense>' => $byNonsense,
            'Emial=<nonsense> (misspelt)' => $misspelt,
            'Tags=<this run>' => $byTag,
            'Tags=<nonsense>' => $byWrongTag,
        ]);
        $verdict($byEmail?->id === $id('contact') && $byNonsense === 0, 'the Email filter is honoured', 'the Email filter is NOT honoured');
        $verdict($misspelt === $unfiltered, 'a misspelt filter is silently ignored', 'a misspelt filter changed the result');
        $verdict($byTag >= 1 && $byWrongTag === 0, 'the Tags filter is honoured', 'the Tags filter is NOT honoured');

        $io->line();
        $io->info('  does LastModifiedFromDate honour a time?');

        $utc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $around = count($saasu->connection()->get('Contacts', [
            'LastModifiedFromDate' => $utc->modify('-1 hour')->format('Y-m-d\TH:i:s'),
            'LastModifiedToDate' => $utc->modify('+1 hour')->format('Y-m-d\TH:i:s'),
        ])->collection('Contacts'));
        $later = count($saasu->connection()->get('Contacts', [
            'LastModifiedFromDate' => $utc->modify('+1 hour')->format('Y-m-d\TH:i:s'),
            'LastModifiedToDate' => $utc->modify('+2 hours')->format('Y-m-d\TH:i:s'),
        ])->collection('Contacts'));
        $io->values(['modified in the last hour' => $around, 'modified an hour from now' => $later]);
        $verdict($around >= 1 && $later === 0, 'a time is honoured - a sync can be exact', 'a time was not honoured as expected (it may be read as a whole day)');

        $io->line();
        $io->info('  does a PUT clear what it does not send?');

        // On a contact of its own: the probe blanks what it does not send, and the customer above
        // still needs its email address for the invoice emails.
        $probe = $saasu->contacts()->create(
            ContactRequest::person('ZZ Harness PUT probe', $stamp)
                ->asCustomer()
                ->withEmailAddress('saasu-harness-probe@example.invalid')
                ->withPositionTitle('kept unless a PUT clears it')
                ->withTags(['saasu-harness'])
        );
        $probeRead = $saasu->contacts()->get((int) $probe->id);
        $saasu->contacts()->update((int) $probe->id, ContactRequest::make()
            ->withGivenName('ZZ Harness PUT probe')
            ->withFamilyName($stamp)
            ->withLastUpdatedId($probeRead->lastUpdatedId));
        $after = $saasu->contacts()->get((int) $probe->id);
        $io->values([
            'PositionTitle after' => var_export($after->positionTitle, true),
            'Tags after' => implode(', ', $after->tags) ?: '(none)',
            'EmailAddress after' => var_export($after->emailAddress, true),
            'IsCustomer after' => var_export($after->isCustomer, true),
        ]);
        $verdict($after->positionTitle !== null, 'a PUT keeps what it was not sent', 'A PUT CLEARS OMITTED FIELDS - from() is essential');

        $io->line();
        $io->info('  a stale LastUpdatedId');

        try {
            $saasu->contacts()->update((int) $probe->id, ContactRequest::from($probeRead)->withPositionTitle('stale'));
            $io->warn('  the stale write was ACCEPTED');
        } catch (ConcurrencyException $e) {
            $io->success('  refused as a ConcurrencyException: ' . implode(' ', $e->messages()));
        } catch (ExceptionInterface $e) {
            $io->warn(sprintf('  refused, but not recognised as a conflict: %s - %s', $e::class, $e->getMessage()));
        }
    });

    $step('contact aggregate', static function () use ($saasu, $stamp, $remember, $io): void {
        $saved = $saasu->contactAggregates()->create(
            ContactAggregateRequest::person('ZZ Aggregate', $stamp)
                ->withCompany(AggregateCompanyRequest::make()->withName("ZZ Aggregate Co {$stamp}"))
                ->withContactManager(ContactManagerRequest::make()->withGivenName('ZZ Manager')->withFamilyName($stamp))
        );

        $io->values([
            'contact' => $saved->id,
            'company' => $saved->companyId() ?? '(none)',
            'manager' => $saved->contactManagerId() ?? '(none)',
            'raw keys' => implode(', ', array_keys($saved->raw)),
        ]);

        if ($saved->companyId() !== null) {
            $remember("aggregate company #{$saved->companyId()}", static fn () => $saasu->companies()->delete((int) $saved->companyId()));
        }

        if ($saved->contactManagerId() !== null) {
            $remember("aggregate manager #{$saved->contactManagerId()}", static fn () => $saasu->contacts()->delete((int) $saved->contactManagerId()));
        }

        $remember("aggregate contact #{$saved->id}", static fn () => $saasu->contacts()->delete((int) $saved->id));

        $aggregate = $saasu->contactAggregates()->get((int) $saved->id);
        $io->values(['read back' => sprintf('%s, company %s, manager %s', $aggregate->familyName, $aggregate->company?->name, $aggregate->contactManager?->givenName)]);
    });

    // ---- inventory -----------------------------------------------------------------------

    $step('inventory: two items, a combo, an adjustment, a build and a transfer', static function () use ($saasu, $stamp, $remember, &$ids, $id, $io, $wire, $saleTax, $purchaseTax, $today, $verdict): void {
        foreach (['itemA' => 'A', 'itemB' => 'B'] as $key => $letter) {
            $saved = $saasu->items()->create(
                ItemRequest::inventory("ZZ-{$letter}-{$stamp}", "ZZ Harness item {$letter}")
                    ->withInventoried(true)
                    ->withAssetAccountId($id('asset'))
                    ->withSaleCostOfSalesAccountId($id('cos'))
                    ->sold($id('income'), 25.00, $saleTax)
                    ->bought($id('expense'), 10.00, $purchaseTax)
            );
            $ids[$key] = (int) $saved->id;
            $remember("item {$letter} #{$saved->id}", static fn () => $saasu->items()->delete((int) $saved->id));
        }

        $combo = $saasu->items()->create(
            ItemRequest::combo("ZZ-C-{$stamp}", 'ZZ Harness combo', [BuildItemRequest::of($id('itemA'), 2)])
                ->withInventoried(true)
                ->withAssetAccountId($id('asset'))
                ->withSaleCostOfSalesAccountId($id('cos'))
                ->sold($id('income'), 60.00, $saleTax)
        );
        $ids['combo'] = (int) $combo->id;
        $remember("combo #{$combo->id}", static fn () => $saasu->items()->delete((int) $combo->id));

        $adjustment = $saasu->itemAdjustments()->create(ItemAdjustmentRequest::of($today, [
            AdjustmentItemRequest::of($id('itemA'), $id('expense'), 10, 5.00),
            AdjustmentItemRequest::of($id('itemB'), $id('expense'), 5, 5.00),
        ])->withSummary("ZZ Harness stocktake {$stamp}"));
        $remember("adjustment #{$adjustment->id}", static fn () => $saasu->itemAdjustments()->delete((int) $adjustment->id));

        $wire($saasu->itemAdjustments()->get((int) $adjustment->id), ['Date', 'CreatedDateUtc'], 'adjustment');

        $io->values(['item A on hand after adjustment' => $saasu->items()->get($id('itemA'))->stockOnHand]);
        $io->values(['build combo x1' => $saasu->items()->build($id('combo'), 1)]);
        $io->values([
            'item A on hand after build' => $saasu->items()->get($id('itemA'))->stockOnHand,
            'combo on hand after build' => $saasu->items()->get($id('combo'))->stockOnHand,
        ]);

        $transfer = $saasu->itemTransfers()->create(ItemTransferRequest::of($today, [
            TransferItemRequest::of($id('itemA'), -1, 5.00),
            TransferItemRequest::of($id('itemB'), 1, 5.00),
        ])->withSummary("ZZ Harness transfer {$stamp}"));
        $remember("transfer #{$transfer->id}", static fn () => $saasu->itemTransfers()->delete((int) $transfer->id));

        $io->values([
            'item A on hand after transfer' => $saasu->items()->get($id('itemA'))->stockOnHand,
            'item B on hand after transfer' => $saasu->items()->get($id('itemB'))->stockOnHand,
        ]);

        $bySearch = count($saasu->items()->list(ItemFilter::all()->search("ZZ-A-{$stamp}")));
        $byNonsense = count($saasu->items()->list(ItemFilter::all()->search('zzz-no-such-item')));
        $io->values(['items, SearchText=<item A>' => $bySearch, 'items, SearchText=<nonsense>' => $byNonsense]);
        $verdict($bySearch === 1 && $byNonsense === 0, 'the item search filter is honoured', 'the item search filter is NOT honoured');
    });

    // ---- invoices ------------------------------------------------------------------------

    $step('invoices: service, item with quick payment and email, foreign currency, purchase', static function () use ($saasu, $stamp, $emailTo, $remember, &$ids, $id, $io, $wire, $today): void {
        $service = $saasu->invoices()->create(
            InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, $today)
                ->withBillingContactId($id('contact'))
                ->withAutoNumber()
                ->withTaxInclusive(true)
                ->withDueDate($today->modify('+14 days'))
                ->withSummary("ZZ Harness service invoice {$stamp}")
                ->withTags(['saasu-harness'])
                ->addLineItem(InvoiceLineItemRequest::service('Consulting', $id('income'), 110.00, 'G1'))
        );
        $ids['invoice'] = (int) $service->id;
        $remember("invoice #{$service->id}", static fn () => $saasu->invoices()->delete((int) $service->id));
        $io->values(['service invoice' => sprintf('#%d, number %s', $service->id, var_export($service->generatedInvoiceNumber(), true))]);

        $item = InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Item, $today)
            ->withBillingContactId($id('contact'))
            ->withAutoNumber()
            ->withTaxInclusive(true)
            ->withSummary("ZZ Harness item invoice {$stamp}")
            ->addLineItem(InvoiceLineItemRequest::item($id('itemB'), 1, 25.00, 'G1'))
            ->withQuickPayment(QuickPaymentRequest::of($today, $id('bank'), 10.00));

        if ($emailTo !== '') {
            $item = $item->withEmailToContact(EmailMessageRequest::of($emailTo, $emailTo, "Saasu harness {$stamp}: email on insert", 'Sent by the saasu-api harness.'));
        }

        $itemSaved = $saasu->invoices()->create($item);
        $ids['itemInvoice'] = (int) $itemSaved->id;
        $remember("item invoice #{$itemSaved->id}, and its quick payment", static function () use ($saasu, $itemSaved): void {
            foreach ($saasu->payments()->forInvoice((int) $itemSaved->id) as $payment) {
                $saasu->payments()->delete((int) $payment->id);
            }

            $saasu->invoices()->delete((int) $itemSaved->id);
        });
        $itemRead = $saasu->invoices()->get($id('itemInvoice'));
        $io->values([
            'item invoice' => sprintf('#%d, sent to contact %s', $itemSaved->id, var_export($itemSaved->sentToContact(), true)),
            'quick payment applied' => var_export($itemRead->amountPaid, true),
            'owed' => var_export($itemRead->amountOwed, true),
        ]);

        $usd = $saasu->invoices()->create(
            InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, $today)
                ->withBillingContactId($id('contact'))
                ->withAutoNumber()
                ->withCurrency('USD')
                ->withAutoPopulateFxRate(true)
                ->withSummary("ZZ Harness USD invoice {$stamp}")
                ->addLineItem(InvoiceLineItemRequest::service('Export consulting', $id('income'), 100.00))
        );
        $remember("USD invoice #{$usd->id}", static fn () => $saasu->invoices()->delete((int) $usd->id));
        $usdRead = $saasu->invoices()->get((int) $usd->id);
        $io->values(['USD invoice' => sprintf('#%d, currency %s, FxRate %s', $usd->id, $usdRead->currency, var_export($usdRead->fxRate, true))]);

        $purchase = $saasu->invoices()->create(
            InvoiceRequest::purchase(InvoiceType::TaxInvoice, InvoiceLayout::Service, $today)
                ->withBillingContactId($id('contact'))
                ->withInvoiceNumber("ZZ-P-{$stamp}")
                ->withTaxInclusive(true)
                ->withSummary("ZZ Harness purchase {$stamp}")
                ->addLineItem(InvoiceLineItemRequest::service('Supplies', $id('expense'), 55.00, 'G11'))
        );
        $remember("purchase #{$purchase->id}", static fn () => $saasu->invoices()->delete((int) $purchase->id));

        $read = $saasu->invoices()->get($id('invoice'));
        $wire($read, ['TransactionDate', 'DueDate', 'CreatedDateUtc', 'TotalAmount', 'TotalTaxAmount', 'AmountOwed', 'InvoiceNumber', 'Terms'], 'invoice');

        $io->line();
        $io->info('  invoice filters');

        $all = count($saasu->invoices()->list());
        $sales = count($saasu->invoices()->list(InvoiceFilter::sales()));
        $purchases = count($saasu->invoices()->list(InvoiceFilter::purchases()));
        $byNumber = count($saasu->invoices()->list(InvoiceFilter::all()->invoiceNumber("ZZ-P-{$stamp}")));
        $io->values(['invoices, unfiltered' => $all, 'sales' => $sales, 'purchases' => $purchases, 'InvoiceNumber=<the purchase>' => $byNumber]);
    });

    $step('invoice PDF, attachment, emails', static function () use ($saasu, $stamp, $emailTo, $remember, &$ids, $id, $io): void {
        $pdf = $saasu->invoices()->pdf($id('invoice'));
        $io->values([
            'PDF' => sprintf('%s, %d bytes, %s, filename %s', $pdf->isPdf() ? 'a PDF' : 'NOT a PDF', $pdf->size(), $pdf->contentType ?? '(no type)', $pdf->filename ?? '(none)'),
        ]);

        $content = "saasu-api harness attachment {$stamp}\n";
        $attachmentId = $saasu->attachments()->create(AttachmentRequest::of($id('invoice'), "zz-harness-{$stamp}.txt", $content, 'ZZ Harness'));
        $io->values(['attachment id' => var_export($attachmentId, true)]);

        if ($attachmentId !== null) {
            $remember("attachment #{$attachmentId}", static fn () => $saasu->attachments()->delete($attachmentId));

            $raw = Json::decode((string) harness_raw($saasu, 'GET', 'InvoiceAttachment/' . $attachmentId)->getBody())[1];
            $data = Cast::array($raw)['AttachmentData'] ?? null;
            $read = $saasu->attachments()->get($attachmentId);
            $io->values([
                'AttachmentData is' => is_string($data) ? 'a string (base64)' : (is_array($data) ? 'an ARRAY of ' . count($data) : var_export($data, true)),
                'content round-trips' => $read->content() === $content ? 'yes' : 'NO',
                'listed on the invoice' => count($saasu->invoices()->attachments($id('invoice'))),
            ]);
        }

        if ($emailTo === '') {
            $io->warn('  SAASU_EMAIL_TO is empty - the email endpoints are not exercised');

            return;
        }

        $io->values(['email to an address' => var_export($saasu->invoices()->email($id('invoice'), $emailTo), true)]);
        $io->values(['email to the contact' => var_export($saasu->invoices()->emailToContact($id('invoice')), true)]);
    });

    $step('invoice updates: from(), a PUT without tags, a stale id', static function () use ($saasu, &$ids, $id, $io, $verdict): void {
        $invoice = $saasu->invoices()->get($id('invoice'));
        $saved = $saasu->invoices()->update($id('invoice'), InvoiceRequest::from($invoice)->withSummary($invoice->summary . ' (updated)'));
        $after = $saasu->invoices()->get($id('invoice'));
        $io->values([
            'Saved id' => var_export($saved->id, true),
            'summary after' => $after->summary,
            'lines after' => count($after->lineItems),
        ]);

        $saasu->invoices()->update($id('invoice'), InvoiceRequest::from($after)->without('Tags'));
        $untagged = $saasu->invoices()->get($id('invoice'));
        $io->values(['tags after a PUT that omitted them' => implode(', ', $untagged->tags) ?: '(none)']);
        $verdict($untagged->tags !== [], 'an invoice PUT keeps omitted tags', 'an invoice PUT CLEARS omitted tags');

        try {
            $saasu->invoices()->update($id('invoice'), InvoiceRequest::from($invoice));
            $io->warn('  the stale invoice write was ACCEPTED');
        } catch (ConcurrencyException $e) {
            $io->success('  stale invoice write refused: ' . implode(' ', $e->messages()));
        }
    });

    // ---- payments, journals, activities ----------------------------------------------------

    $step('a payment against the service invoice', static function () use ($saasu, $remember, &$ids, $id, $io, $wire, $today, $todayRange): void {
        $saved = $saasu->payments()->create(
            PaymentRequest::received($today, $id('bank'), [PaymentItemRequest::of($id('invoice'), 50.00)])
                ->withClearedDate($today)
                ->withReference('ZZ Harness')
        );
        $ids['payment'] = (int) $saved->id;
        $remember("payment #{$saved->id}", static fn () => $saasu->payments()->delete((int) $saved->id));

        $wire($saasu->payments()->get((int) $saved->id), ['TransactionDate', 'ClearedDate', 'TransactionType', 'CreatedDateUtc'], 'payment');

        $io->values([
            'payments, no range' => count($saasu->payments()->list()),
            'payments, paid today' => count($saasu->payments()->list(PaymentFilter::all()->paidBetween($todayRange))),
            'payments for the invoice' => count($saasu->payments()->forInvoice($id('invoice'))),
            'payments for the item invoice (quick)' => count($saasu->payments()->forInvoice($id('itemInvoice'))),
            'invoice owes after' => var_export($saasu->invoices()->get($id('invoice'))->amountOwed, true),
        ]);

        foreach ($saasu->accounts()->bankBalances() as $balance) {
            if ($balance->accountId === $id('bank')) {
                $io->values(['bank balance' => var_export($balance->balance, true)]);
            }
        }
    });

    $step('a journal', static function () use ($saasu, $stamp, $remember, &$ids, $id, $wire, $today, $io): void {
        $request = JournalRequest::of($today, [
            JournalItemRequest::debit($id('expense'), 20.00),
            JournalItemRequest::credit($id('bank'), 20.00),
        ])->withSummary("ZZ Harness journal {$stamp}")->withReference('ZZ');

        $io->values(['imbalance before sending' => $request->imbalance()]);

        $saved = $saasu->journals()->create($request);
        $ids['journal'] = (int) $saved->id;
        $remember("journal #{$saved->id}", static fn () => $saasu->journals()->delete((int) $saved->id));

        $wire($saasu->journals()->get((int) $saved->id), ['TransactionDate', 'Items'], 'journal');
    });

    $step('an activity on the customer', static function () use ($saasu, $stamp, $remember, &$ids, $id, $wire, $today): void {
        $saved = $saasu->activities()->create(
            ActivityRequest::of("ZZ Harness follow-up {$stamp}")
                ->attachedTo(AttachedToType::Contact, $id('contact'))
                ->withDue($today->setTime(17, 0))
                ->withOwnerEmail($saasu->user()->get()->username)
                ->withDetails('Created by the saasu-api harness.')
        );
        $remember("activity #{$saved->id}", static fn () => $saasu->activities()->delete((int) $saved->id));

        $wire($saasu->activities()->get((int) $saved->id), ['Due', 'AttachedToType', 'OwnerEmail'], 'activity');
    });

    // ---- reading it back as a whole -------------------------------------------------------

    $step('search, reports, sales summary, file identity', static function () use ($saasu, $stamp, $io, $today, $todayRange): void {
        $results = $saasu->search()->query($stamp);
        $io->values([
            'search: transactions' => $results->totalTransactions,
            'search: contacts' => $results->totalContacts,
            'search: items' => $results->totalItems,
        ]);

        $year = (int) $today->format('n') >= 7 ? (int) $today->format('Y') + 1 : (int) $today->format('Y');
        $report = $saasu->reports()->profitAndLoss(ReportFilter::all()->period(DateRange::financialYear($year)));
        $io->values([
            'P&L total income' => var_export($report->totalIncome?->amount, true),
            'P&L net profit' => var_export($report->netProfit?->amount, true),
            'P&L income lines' => count($report->incomes),
        ]);

        $summary = $saasu->invoices()->salesSummary($todayRange);
        $io->values(['sales today' => sprintf('total %s, owed %s, count %s', var_export($summary->total, true), var_export($summary->totalOwed, true), var_export($summary->totalCount, true))]);

        $identity = $saasu->files()->identity();

        try {
            $saved = $saasu->files()->updateIdentity(FileIdentityRequest::from($identity));
            $io->values(['file identity PUT, unchanged' => sprintf('LastUpdatedId %s -> %s', $identity->lastUpdatedId, $saved->lastUpdatedId)]);
        } catch (ValidationException $e) {
            // Expected on a file whose identity was never filled in: measured 2026-09-14, the PUT
            // needs an ABN and a full address. Reported, not counted as a failure.
            $io->warn('  file identity PUT refused, as an unfilled identity is: ' . $e->getMessage());
        }
    });

    // ---- payroll ---------------------------------------------------------------------------

    $step('payroll: a timesheet and a leave request, if the file has an employee', static function () use ($saasu, $remember, &$ids, $id, $io, $wire, $today): void {
        $employees = $saasu->employees()->all(EmployeeFilter::all()->withLeaveBalances());
        $io->values(['employees' => count($employees)]);

        if ($employees === []) {
            $io->warn('  no employees - the API has no endpoint to create one, so timesheets and leave go untested');
            $io->line('  probing with the customer as the employee, to record what Saasu says:');

            try {
                $saasu->timesheets()->create(TimesheetRequest::of($id('contact'), $today->setTime(9, 0), $today->setTime(10, 0)));
                $io->warn('  a timesheet for a non-employee was ACCEPTED');
            } catch (ExceptionInterface $e) {
                $io->line('  refused: ' . $e->getMessage());
            }

            return;
        }

        $employee = $employees[0];
        $timesheet = $saasu->timesheets()->create(TimesheetRequest::of((int) $employee->id, $today->setTime(9, 0), $today->setTime(17, 0), 30));
        $remember("timesheet #{$timesheet->id}", static fn () => $saasu->timesheets()->delete((int) $timesheet->id));
        $wire($saasu->timesheets()->get((int) $timesheet->id), ['StartTime', 'FinishTime', 'ContactId'], 'timesheet');

        $entitlements = $saasu->employees()->entitlements();

        if ($entitlements !== []) {
            $tomorrow = $today->modify('+1 day');
            $leave = $saasu->leaveRequests()->create(
                LeaveRequestRequest::of((int) $employee->id, (int) $entitlements[0]->id, $tomorrow, $tomorrow)
                    ->withItems([LeaveRequestItemRequest::of($tomorrow, 7.6)])
                    ->withNotes('ZZ Harness')
            );
            $remember("leave request #{$leave->id}", static fn () => $saasu->leaveRequests()->delete((int) $leave->id));
            $read = $saasu->leaveRequests()->get((int) $leave->id);
            $wire($read, ['StartDate', 'LastModifiedDateUtc', 'Status'], 'leave');
            $saasu->leaveRequests()->update((int) $leave->id, LeaveRequestRequest::from($read)->withNotes('ZZ Harness (updated)'));
            $io->success('  leave request updated with its LastModifiedDateUtc');
        }
    });
} finally {
    $io->line();
    $io->info(sprintf('cleanup: sweeping every ZZ record from file %s, in dependency order', $fileId));
    $io->line(sprintf('  this run created %d; a sweep also takes anything an earlier run left', count($cleanup)));

    $leaks = harness_sweep($saasu, $io);

    try {
        $io->line();
        $io->info('what a deleted record answers');

        foreach (['Invoice' => $ids['invoice'] ?? null, 'Contact' => $ids['contact'] ?? null, 'Item' => $ids['itemA'] ?? null, 'Account' => $ids['bank'] ?? null, 'Journal' => $ids['journal'] ?? null, 'Payment' => $ids['payment'] ?? null] as $resource => $recordId) {
            if ($recordId !== null) {
                $io->values([$resource => harness_describe(harness_raw($saasu, 'GET', "{$resource}/{$recordId}"), 100)]);
            }
        }

        $deleted = $saasu->deletedEntities()->all(DeletedEntityFilter::all()->deletedBetween(DateRange::between($today->modify('-1 day'), $today->modify('+1 day'))));
        $types = array_count_values(array_map(static fn ($entity): string => (string) $entity->entityType, $deleted));
        $io->values(['deleted entities, yesterday to tomorrow' => json_encode($types) ?: '{}']);
    } catch (ExceptionInterface $e) {
        $io->warn('  ' . $e->getMessage());
    }

    harness_spent($io, $saasu);
}

$io->line();

if ($leaks !== []) {
    $io->error(sprintf('✗ %d records left behind in file %s: %s', count($leaks), $fileId, implode('; ', $leaks)));
}

if ($failures > 0) {
    $io->error(sprintf('%d step(s) failed - read above', $failures));
}

if ($leaks !== [] || $failures > 0) {
    exit(1);
}

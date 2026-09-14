<?php

/**
 * Exercise: an update built from() a record changes nothing - for every writable resource.
 * WRITES. Refuses any file but SAASU_DISPOSABLE_FILE_ID. Sends no email. About 100 requests.
 *
 * Read-only by default. Needs SAASU_WRITE=1, and in an agent session SAASU_AGENT_MAY_WRITE=1 on
 * the command line.
 *
 * WHY THIS IS THE TEST THAT MATTERS. Measured 2026-09-14: a Saasu PUT clears every field it does
 * not send. Every update in this package is built from() the record, and from() copies the fields
 * its builder's WRITABLE list names. So a field Saasu accepts that a WRITABLE list leaves out is a
 * field every update through this package silently erases - and nothing else in the package, the
 * suite included, would ever notice, because the builder and its test agree with each other.
 *
 * For each resource it creates a record with as many fields filled as it can, reads it, sends
 * from() of it back unchanged, reads it again, and compares the two reads field by field -
 * nested lines included. Any difference is data an update lost or changed. Fields that move on
 * every write (LastUpdatedId, the modified timestamps, _links) are left out of the comparison.
 *
 * It also lists, per resource, every top-level field the read carried that from() does not send.
 * Each is either read-only - a total, a timestamp, a name copied from a contact - or a gap in a
 * WRITABLE list. The comparison says which: a gap shows up as a difference.
 *
 * FIRST RUN, 2026-09-14: it found one gap - a contact update erased the contact's CreatedDateUtc,
 * which ContactRequest now carries - and one expected change, the line ids of a service invoice,
 * which Saasu replaces on every update. Every other resource round-tripped unchanged.
 *
 * Cleanup is the shared ZZ sweep, in a `finally`.
 *
 * @var Hampel\Rig\Io $io
 */

use Hampel\Saasu\Api\Entity\Entity;
use Hampel\Saasu\Api\Enum\AccountLevel;
use Hampel\Saasu\Api\Enum\AccountType;
use Hampel\Saasu\Api\Enum\AttachedToType;
use Hampel\Saasu\Api\Enum\InvoiceLayout;
use Hampel\Saasu\Api\Enum\InvoiceType;
use Hampel\Saasu\Api\Enum\TradingTermsIntervalType;
use Hampel\Saasu\Api\Exception\ApiException;
use Hampel\Saasu\Api\Exception\ExceptionInterface;
use Hampel\Saasu\Api\Request\AccountRequest;
use Hampel\Saasu\Api\Request\ActivityRequest;
use Hampel\Saasu\Api\Request\AddressRequest;
use Hampel\Saasu\Api\Request\AdjustmentItemRequest;
use Hampel\Saasu\Api\Request\AggregateCompanyRequest;
use Hampel\Saasu\Api\Request\BpayDetailsRequest;
use Hampel\Saasu\Api\Request\BuildItemRequest;
use Hampel\Saasu\Api\Request\ChequeDetailsRequest;
use Hampel\Saasu\Api\Request\CompanyRequest;
use Hampel\Saasu\Api\Request\ContactAggregateRequest;
use Hampel\Saasu\Api\Request\ContactManagerRequest;
use Hampel\Saasu\Api\Request\ContactRequest;
use Hampel\Saasu\Api\Request\DirectDepositDetailsRequest;
use Hampel\Saasu\Api\Request\InvoiceLineItemRequest;
use Hampel\Saasu\Api\Request\InvoiceRequest;
use Hampel\Saasu\Api\Request\InvoiceTermsRequest;
use Hampel\Saasu\Api\Request\ItemAdjustmentRequest;
use Hampel\Saasu\Api\Request\ItemRequest;
use Hampel\Saasu\Api\Request\ItemTransferRequest;
use Hampel\Saasu\Api\Request\JournalItemRequest;
use Hampel\Saasu\Api\Request\JournalRequest;
use Hampel\Saasu\Api\Request\Payload;
use Hampel\Saasu\Api\Request\PaymentItemRequest;
use Hampel\Saasu\Api\Request\PaymentRequest;
use Hampel\Saasu\Api\Request\TradingTermsRequest;
use Hampel\Saasu\Api\Request\TransferItemRequest;

require __DIR__ . '/lib/harness.php';
require __DIR__ . '/lib/sweep.php';

$io->title('saasu · round-trip write');

$fileId = harness_require_disposable_file($io);

$live = harness_mode($io, 'SAASU_WRITE', 'SAASU_AGENT_MAY_WRITE', sprintf(
    'file %s will get one record of every writable kind, each updated unchanged, then be swept',
    $fileId
));

$saasu = harness_client($io, 250);

if (!$live) {
    harness_spent($io, $saasu);

    exit(0);
}

/**
 * Fields that change on every write, and so say nothing about data loss.
 */
const ROUNDTRIP_VOLATILE = ['_links', 'LastUpdatedId', 'LastModifiedDateUtc', 'LastModifiedByUserId', 'LastModifiedBy', 'UtcLastModified'];

/**
 * @return array<string, mixed>
 */
function roundtrip_flatten(mixed $value, string $prefix = ''): array
{
    if (!is_array($value)) {
        return [$prefix => $value];
    }

    if ($value === []) {
        return [$prefix => []];
    }

    $flat = [];

    foreach ($value as $key => $item) {
        if (is_string($key) && in_array($key, ROUNDTRIP_VOLATILE, true)) {
            continue;
        }

        $flat += roundtrip_flatten($item, $prefix === '' ? (string) $key : $prefix . '.' . $key);
    }

    return $flat;
}

$stamp = date('Ymd-His');
$today = new DateTimeImmutable('now', new DateTimeZone('Australia/Sydney'));
$failures = 0;
$lossy = [];
$leaks = [];

/**
 * Read, update from() unchanged, read again, and compare.
 *
 * @param  class-string<Payload>  $request
 * @param  Closure(): Entity  $read
 * @param  Closure(Payload): mixed  $write
 */
$roundTrip = static function (string $label, string $request, Closure $read, Closure $write) use ($io, &$failures, &$lossy): void {
    $io->line();
    $io->info($label);

    try {
        $before = $read();
        $write($request::from($before));
        $after = $read();
    } catch (ExceptionInterface|RuntimeException|TypeError $e) {
        $failures++;
        $io->error('  ' . $e::class . ': ' . $e->getMessage());

        if ($e instanceof ApiException && $e->body !== '') {
            $io->line('  raw body: ' . substr(str_replace(["\r", "\n"], ' ', $e->body), 0, 400));
        }

        return;
    }

    $writable = (new ReflectionClassConstant($request, 'WRITABLE'))->getValue();
    $unsent = array_values(array_diff(array_keys($before->raw), $writable, ROUNDTRIP_VOLATILE));

    $a = roundtrip_flatten($before->raw);
    $b = roundtrip_flatten($after->raw);
    $changed = [];
    $expected = [];

    foreach (array_unique([...array_keys($a), ...array_keys($b)]) as $path) {
        $was = array_key_exists($path, $a) ? $a[$path] : '(absent)';
        $now = array_key_exists($path, $b) ? $b[$path] : '(absent)';

        // Measured 2026-09-14: a service invoice's lines get new ids on every update - Saasu
        // replaces them rather than editing them - while an item invoice's keep theirs. Nothing is
        // lost, so it is reported as expected rather than counted.
        if (preg_match('/^LineItems\.\d+\.Id$/', $path) === 1 && $was !== $now) {
            $expected[$path] = sprintf('%s -> %s', json_encode($was), json_encode($now));

            continue;
        }

        if ($was !== $now && !(is_numeric($was) && is_numeric($now) && abs((float) $was - (float) $now) < 0.0000001)) {
            $changed[$path] = sprintf('%s -> %s', json_encode($was), json_encode($now));
        }
    }

    $io->values([
        'fields compared' => count($a),
        'not sent by from()' => implode(', ', $unsent) ?: '(none)',
    ]);

    if ($expected !== []) {
        $io->line('  line ids replaced, as Saasu does on this kind of invoice: ' . implode(', ', array_keys($expected)));
    }

    if ($changed === []) {
        $io->success('  unchanged - nothing lost');
    } else {
        $lossy[$label] = array_keys($changed);
        $io->warn(sprintf('  %d field(s) CHANGED by an unchanged update:', count($changed)));
        $io->values($changed);
    }
};

$step = static function (string $label, Closure $probe) use ($io, &$failures): mixed {
    try {
        return $probe();
    } catch (ExceptionInterface|RuntimeException|TypeError $e) {
        $failures++;
        $io->error(sprintf('%s: %s: %s', $label, $e::class, $e->getMessage()));

        if ($e instanceof ApiException && $e->body !== '') {
            $io->line('  raw body: ' . substr(str_replace(["\r", "\n"], ' ', $e->body), 0, 400));
        }

        return null;
    }
};

try {
    $io->info('setup');

    $base = $saasu->files()->identity()->currencyCode ?? 'AUD';
    $taxCodes = [];

    foreach ($saasu->taxCodes()->all() as $code) {
        $taxCodes[(string) $code->code] = (int) $code->id;
    }

    $header = (int) $saasu->accounts()->create(AccountRequest::of("ZZ Roundtrip Header {$stamp}", AccountType::Income, $base, AccountLevel::Header))->id;
    $income = (int) $saasu->accounts()->create(
        AccountRequest::of("ZZ Roundtrip Income {$stamp}", AccountType::Income, $base)
            ->withHeaderAccountId($header)
            ->withDefaultTaxCode('G1')
            ->withLedgerCode('ZZ' . substr($stamp, -4))
    )->id;
    $expense = (int) $saasu->accounts()->create(AccountRequest::of("ZZ Roundtrip Expense {$stamp}", AccountType::Expense, $base))->id;
    $asset = (int) $saasu->accounts()->create(AccountRequest::of("ZZ Roundtrip Stock {$stamp}", AccountType::Asset, $base))->id;
    $cos = (int) $saasu->accounts()->create(AccountRequest::of("ZZ Roundtrip COS {$stamp}", AccountType::CostOfSales, $base))->id;
    $bank = (int) $saasu->accounts()->create(
        AccountRequest::of("ZZ Roundtrip Bank {$stamp}", AccountType::Asset, $base)
            ->withBankAccount(true)
            ->withBankAccountName('ZZ Roundtrip')
            ->withBsb('062000')
            ->withAccountNumber('55555555')
            ->withBankFileCreationEnabled(true)
            ->withBankCode('CBA')
            ->withUserNumber('123456')
            ->withMerchantFeeAccountId($expense)
            // Only a bank account may be in the forecaster - measured 2026-09-14, 400 "Cannot
            // include non bank accounts in Forecaster".
            ->withIncludeInForecaster(true)
    )->id;

    $company = (int) $saasu->companies()->create(
        CompanyRequest::of("ZZ Roundtrip Co {$stamp}")
            ->withAbn('51824753556')
            ->withWebsite('https://example.com')
            ->withLongDescription('A company with every field filled.')
            ->withTradingName('ZZ Roundtrip Trading')
            ->withCompanyEmail('company@example.invalid')
    )->id;

    $supplier = (int) $saasu->contacts()->create(ContactRequest::person('ZZ Roundtrip supplier', $stamp)->asSupplier())->id;
    $manager = (int) $saasu->contacts()->create(ContactRequest::person('ZZ Roundtrip manager', $stamp))->id;

    $contact = (int) $saasu->contacts()->create(
        ContactRequest::person('ZZ Roundtrip', $stamp)
            ->withSalutation('Dr.')
            ->withMiddleInitials('Q')
            ->asCustomer()
            ->withContractor(true)
            ->withPartner(true)
            ->withCompanyId($company)
            ->withContactManagerId($manager)
            ->withPositionTitle('Director')
            ->withEmailAddress('roundtrip@example.invalid')
            ->withAdditionalEmailList(['a@example.invalid', 'b@example.invalid'])
            ->withPrimaryPhone('02 9999 0001')
            ->withMobilePhone('0400 000 001')
            ->withHomePhone('02 9999 0002')
            ->withOtherPhone('02 9999 0003')
            ->withFax('02 9999 0004')
            ->withWebsiteUrl('https://example.com/roundtrip')
            ->withContactReference("ZZ-RT-{$stamp}")
            ->withPostalAddress(AddressRequest::of('1 Post St', 'Sydney', 'NSW', '2000', 'Australia'))
            ->withOtherAddress(AddressRequest::of('2 Other Rd', 'Melbourne', 'VIC', '3000', 'Australia'))
            ->withSaleTradingTerms(TradingTermsRequest::dueIn(14))
            ->withPurchaseTradingTerms(TradingTermsRequest::endOfMonthPlus(30))
            ->withDirectDepositDetails(DirectDepositDetailsRequest::make()->withAcceptDirectDeposit(true)->withAccountName('ZZ RT')->withAccountBsb('062000')->withAccountNumber('66666666'))
            ->withChequeDetails(ChequeDetailsRequest::make()->withAcceptCheque(true)->withChequePayableTo('ZZ Roundtrip'))
            ->withBpayDetails(BpayDetailsRequest::make()->withBillerCode('12345')->withCrn('987654321'))
            ->withDefaultSaleDiscount(5.0)
            ->withDefaultPurchaseDiscount(2.5)
            ->withAutoSendStatement(true)
            ->withCustomField1('custom one')
            ->withCustomField2('custom two')
            ->withTwitterId('@zzroundtrip')
            ->withSkypeId('zz.roundtrip')
            ->withLinkedInProfile('zz-roundtrip')
            ->withTags(['saasu-harness', 'roundtrip'])
    )->id;

    $item = (int) $saasu->items()->create(
        ItemRequest::inventory("ZZ-RT-A-{$stamp}", 'ZZ Roundtrip item')
            ->withInventoried(true)
            ->withAssetAccountId($asset)
            ->withSaleCostOfSalesAccountId($cos)
            ->sold($income, 25.00, $taxCodes['G1'] ?? null)
            ->bought($expense, 10.00, $taxCodes['G11'] ?? null)
            ->withLocation('Shelf 3')
            ->withNotes('An item with every field filled.')
            ->withMinimumStockLevel(2)
            ->withDefaultReOrderQuantity(10)
            ->withPrimarySupplierContactId($supplier)
            ->withPrimarySupplierItemCode('SUP-001')
            ->withVisible(true)
    )->id;
    $item2 = (int) $saasu->items()->create(
        ItemRequest::inventory("ZZ-RT-B-{$stamp}", 'ZZ Roundtrip item B')
            ->withInventoried(true)
            ->withAssetAccountId($asset)
            ->withSaleCostOfSalesAccountId($cos)
            ->sold($income, 25.00, $taxCodes['G1'] ?? null)
    )->id;
    $combo = (int) $saasu->items()->create(
        ItemRequest::combo("ZZ-RT-C-{$stamp}", 'ZZ Roundtrip combo', [BuildItemRequest::of($item, 2), BuildItemRequest::of($item2, 1)])
            ->withInventoried(true)
            ->withAssetAccountId($asset)
            ->withSaleCostOfSalesAccountId($cos)
            ->sold($income, 60.00, $taxCodes['G1'] ?? null)
    )->id;

    $io->line('  accounts, a company, three contacts and three items created');

    // ---- the round trips ------------------------------------------------------------------------

    $roundTrip(
        'account (detail, under a header, with tax code and ledger code)',
        'Hampel\Saasu\Api\Request\AccountRequest',
        static fn () => $saasu->accounts()->get($income),
        static fn (Payload $p) => $saasu->accounts()->update($income, $p)
    );

    $roundTrip(
        'bank account (every bank field, merchant fee account)',
        'Hampel\Saasu\Api\Request\AccountRequest',
        static fn () => $saasu->accounts()->get($bank),
        static fn (Payload $p) => $saasu->accounts()->update($bank, $p)
    );

    $roundTrip(
        'company',
        'Hampel\Saasu\Api\Request\CompanyRequest',
        static fn () => $saasu->companies()->get($company),
        static fn (Payload $p) => $saasu->companies()->update($company, $p)
    );

    $roundTrip(
        'contact (every field)',
        'Hampel\Saasu\Api\Request\ContactRequest',
        static fn () => $saasu->contacts()->get($contact),
        static fn (Payload $p) => $saasu->contacts()->update($contact, $p)
    );

    $aggregate = $step('aggregate', static fn () => (int) $saasu->contactAggregates()->create(
        ContactAggregateRequest::person('ZZ Roundtrip aggregate', $stamp)
            ->withPositionTitle('Buyer')
            ->withEmailAddress('aggregate@example.invalid')
            ->withPrimaryPhone('02 9999 0005')
            ->withCustomer(true)
            ->withPostalAddress(AddressRequest::of('3 Aggregate Ln', 'Brisbane', 'QLD', '4000', 'Australia'))
            ->withCompany(AggregateCompanyRequest::make()->withName("ZZ Roundtrip Aggregate Co {$stamp}")->withTradingName('ZZ RT Agg'))
            ->withContactManager(ContactManagerRequest::make()->withGivenName('ZZ Roundtrip agg manager')->withFamilyName($stamp))
    )->id);

    if (is_int($aggregate)) {
        $roundTrip(
            'contact aggregate',
            'Hampel\Saasu\Api\Request\ContactAggregateRequest',
            static fn () => $saasu->contactAggregates()->get($aggregate),
            static fn (Payload $p) => $saasu->contactAggregates()->update($aggregate, $p)
        );
    }

    $roundTrip(
        'inventory item (every field)',
        'Hampel\Saasu\Api\Request\ItemRequest',
        static fn () => $saasu->items()->get($item),
        static fn (Payload $p) => $saasu->items()->update($item, $p)
    );

    $roundTrip(
        'combo item (build items)',
        'Hampel\Saasu\Api\Request\ItemRequest',
        static fn () => $saasu->items()->get($combo),
        static fn (Payload $p) => $saasu->items()->update($combo, $p)
    );

    $serviceInvoice = $step('service invoice', static fn () => (int) $saasu->invoices()->create(
        InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, $today)
            ->withBillingContactId($contact)
            ->withShippingContactId($supplier)
            ->withAutoNumber()
            ->withTaxInclusive(true)
            ->withDueDate($today->modify('+7 days'))
            ->withPurchaseOrderNumber("PO-{$stamp}")
            ->withSummary("ZZ Roundtrip service invoice {$stamp}")
            ->withNotesInternal('internal note')
            ->withNotesExternal('external note')
            ->withRequiresFollowUp(true)
            ->withTerms(InvoiceTermsRequest::dueIn(7, TradingTermsIntervalType::Day))
            ->withTags(['saasu-harness', 'roundtrip'])
            ->addLineItem(InvoiceLineItemRequest::service('Consulting', $income, 110.00, 'G1')->withTags(['line-tag']))
            ->addLineItem(InvoiceLineItemRequest::service('Discounted work', $income, 220.00, 'G1'))
    )->id);

    if (is_int($serviceInvoice)) {
        $roundTrip(
            'service invoice (notes, terms, PO, shipping contact, tags, two lines)',
            'Hampel\Saasu\Api\Request\InvoiceRequest',
            static fn () => $saasu->invoices()->get($serviceInvoice),
            static fn (Payload $p) => $saasu->invoices()->update($serviceInvoice, $p)
        );
    }

    $step('stock for the item invoice', static fn () => $saasu->itemAdjustments()->create(
        ItemAdjustmentRequest::of($today, [AdjustmentItemRequest::of($item2, $expense, 5, 5.00)])->withSummary("ZZ Roundtrip stock {$stamp}")
    ));

    $itemInvoice = $step('item invoice', static fn () => (int) $saasu->invoices()->create(
        InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Item, $today)
            ->withBillingContactId($contact)
            ->withAutoNumber()
            ->withTaxInclusive(true)
            ->withSummary("ZZ Roundtrip item invoice {$stamp}")
            ->addLineItem(InvoiceLineItemRequest::item($item2, 1, 25.00, 'G1', 'Widget')->withLocation('Shelf 3')->withPercentageDiscount(10))
    )->id);


    if (is_int($itemInvoice)) {
        $roundTrip(
            'item invoice (location, percentage discount)',
            'Hampel\Saasu\Api\Request\InvoiceRequest',
            static fn () => $saasu->invoices()->get($itemInvoice),
            static fn (Payload $p) => $saasu->invoices()->update($itemInvoice, $p)
        );
    }

    $purchase = $step('purchase', static fn () => (int) $saasu->invoices()->create(
        InvoiceRequest::purchase(InvoiceType::TaxInvoice, InvoiceLayout::Service, $today)
            ->withBillingContactId($supplier)
            ->withInvoiceNumber("ZZ-RT-P-{$stamp}")
            ->withTaxInclusive(true)
            ->withSummary("ZZ Roundtrip purchase {$stamp}")
            ->inCurrency('USD')
            ->addLineItem(InvoiceLineItemRequest::service('Software', $expense, 55.00))
    )->id);

    if (is_int($purchase)) {
        $roundTrip(
            'foreign-currency purchase',
            'Hampel\Saasu\Api\Request\InvoiceRequest',
            static fn () => $saasu->invoices()->get($purchase),
            static fn (Payload $p) => $saasu->invoices()->update($purchase, $p)
        );
    }

    if (is_int($serviceInvoice)) {
        $payment = $step('payment', static fn () => (int) $saasu->payments()->create(
            PaymentRequest::received($today, $bank, [PaymentItemRequest::of($serviceInvoice, 100.00)])
                ->withFeeAmount(2.00)
                ->withReference("REF-{$stamp}")
                ->withSummary('ZZ Roundtrip payment')
                ->withNotes('payment note')
                ->withClearedDate($today)
                ->withRequiresFollowUp(true)
        )->id);

        if (is_int($payment)) {
            $roundTrip(
                'payment (fee, reference, notes, cleared date)',
                'Hampel\Saasu\Api\Request\PaymentRequest',
                static fn () => $saasu->payments()->get($payment),
                static fn (Payload $p) => $saasu->payments()->update($payment, $p)
            );
        }
    }

    $journal = $step('journal', static fn () => (int) $saasu->journals()->create(
        JournalRequest::of($today, [JournalItemRequest::debit($expense, 20.00), JournalItemRequest::credit($bank, 20.00)])
            ->withSummary("ZZ Roundtrip journal {$stamp}")
            ->withReference("J-{$stamp}")
            ->withNotes('journal note')
            ->withJournalContactId($supplier)
            ->withRequiresFollowUp(true)
            ->withTags(['saasu-harness'])
    )->id);

    if (is_int($journal)) {
        $roundTrip(
            'journal (contact, reference, notes, tags)',
            'Hampel\Saasu\Api\Request\JournalRequest',
            static fn () => $saasu->journals()->get($journal),
            static fn (Payload $p) => $saasu->journals()->update($journal, $p)
        );
    }

    $activity = $step('activity', static fn () => (int) $saasu->activities()->create(
        ActivityRequest::of("ZZ Roundtrip activity {$stamp}")
            ->withDetails('activity details')
            ->withDone(false)
            ->withDue($today->modify('+3 days'))
            ->withOwnerEmail($saasu->user()->get()->username)
            ->attachedTo(AttachedToType::Contact, $contact)
            ->withTags(['saasu-harness'])
    )->id);

    if (is_int($activity)) {
        $roundTrip(
            'activity (details, due, owner, attachment, tags)',
            'Hampel\Saasu\Api\Request\ActivityRequest',
            static fn () => $saasu->activities()->get($activity),
            static fn (Payload $p) => $saasu->activities()->update($activity, $p)
        );
    }

    $adjustment = $step('adjustment', static fn () => (int) $saasu->itemAdjustments()->create(
        ItemAdjustmentRequest::of($today, [AdjustmentItemRequest::of($item, $expense, 10, 5.00), AdjustmentItemRequest::of($item2, $expense, 3, 5.00)])
            ->withSummary("ZZ Roundtrip adjustment {$stamp}")
            ->withNotes('adjustment note')
            ->withRequiresFollowUp(true)
            ->withTags(['saasu-harness'])
    )->id);

    if (is_int($adjustment)) {
        $roundTrip(
            'stock adjustment (notes, tags, two lines)',
            'Hampel\Saasu\Api\Request\ItemAdjustmentRequest',
            static fn () => $saasu->itemAdjustments()->get($adjustment),
            static fn (Payload $p) => $saasu->itemAdjustments()->update($adjustment, $p)
        );
    }

    $transfer = $step('transfer', static fn () => (int) $saasu->itemTransfers()->create(
        ItemTransferRequest::of($today, [TransferItemRequest::of($item, -1, 5.00), TransferItemRequest::of($item2, 1, 5.00)])
            ->withSummary("ZZ Roundtrip transfer {$stamp}")
            ->withNotes('transfer note')
            ->withRequiresFollowUp(true)
            ->withTags(['saasu-harness'])
    )->id);

    if (is_int($transfer)) {
        $roundTrip(
            'stock transfer (notes, tags, two lines)',
            'Hampel\Saasu\Api\Request\ItemTransferRequest',
            static fn () => $saasu->itemTransfers()->get($transfer),
            static fn (Payload $p) => $saasu->itemTransfers()->update($transfer, $p)
        );
    }
} catch (ExceptionInterface|RuntimeException $e) {
    $failures++;
    $io->error($e::class . ': ' . $e->getMessage());
} finally {
    $io->line();
    $io->info('summary');

    if ($failures > 0) {
        $io->warn(sprintf('%d step(s) failed, so not every resource was compared - read above', $failures));
    }

    if ($lossy === []) {
        $io->success('no update that was compared lost or changed a field');
    } else {
        foreach ($lossy as $label => $paths) {
            $io->warn(sprintf('  %s: %s', $label, implode(', ', $paths)));
        }
    }

    $io->line();
    $io->info('cleanup: sweeping every ZZ record');

    $leaks = harness_sweep($saasu, $io);

    harness_spent($io, $saasu);
}

if ($lossy !== [] || $leaks !== [] || $failures > 0) {
    exit(1);
}

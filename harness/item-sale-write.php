<?php

/**
 * Exercise: an item-layout USD sale, booked from nothing and paid with a fee into a USD bank account
 * - the subscription-sale-write flow with an item line instead of a service line. WRITES. Refuses
 * any file but SAASU_DISPOSABLE_FILE_ID. Sends no email. A clean run spends about 52 requests.
 *
 * Read-only by default. Needs SAASU_WRITE=1, and in an agent session SAASU_AGENT_MAY_WRITE=1 on
 * the command line. The file needs multi-currency on.
 *
 * THE QUESTIONS:
 *
 *   1. IS AN ITEM-LAYOUT USD SALE ACCEPTED, and does its line post to the item's sale income
 *      account, at USD 110.00 tax inclusive with an automatic rate - and does the USD payment with
 *      a fee pay it in full, as it does a service-layout sale? An item line reads back with no
 *      account, so where the income went is read from the profit and loss report, by account.
 *   2. WITH NO TAX CODE ON THE LINE, does Saasu fall back to the item's sale tax code, or book no
 *      tax?
 *   3. IS A COMBINED TAX CODE such as "G1,G3" accepted on an item line, and what tax does it book?
 *   4. MUST THE ITEM BE NON-INVENTORIED? Does an inventoried item with no stock on hand refuse the
 *      sale, or sell into negative stock?
 *   5. DOES THE LINE'S DESCRIPTION OVERRIDE THE ITEM'S on the saved invoice?
 *
 * Cleanup is the shared ZZ sweep, in a `finally`.
 *
 * @var Hampel\Rig\Io $io
 */

use Hampel\Saasu\Api\Client;
use Hampel\Saasu\Api\Entity\Invoice;
use Hampel\Saasu\Api\Enum\AccountType;
use Hampel\Saasu\Api\Enum\InvoiceLayout;
use Hampel\Saasu\Api\Enum\InvoiceType;
use Hampel\Saasu\Api\Exception\ApiException;
use Hampel\Saasu\Api\Exception\ExceptionInterface;
use Hampel\Saasu\Api\Exception\ValidationException;
use Hampel\Saasu\Api\Filter\DateRange;
use Hampel\Saasu\Api\Filter\ReportFilter;
use Hampel\Saasu\Api\Request\AccountRequest;
use Hampel\Saasu\Api\Request\ContactRequest;
use Hampel\Saasu\Api\Request\InvoiceLineItemRequest;
use Hampel\Saasu\Api\Request\InvoiceRequest;
use Hampel\Saasu\Api\Request\ItemRequest;
use Hampel\Saasu\Api\Request\PaymentItemRequest;
use Hampel\Saasu\Api\Request\PaymentRequest;

require __DIR__ . '/lib/harness.php';
require __DIR__ . '/lib/sweep.php';

$io->title('saasu · item sale write');

$fileId = harness_require_disposable_file($io);

$live = harness_mode($io, 'SAASU_WRITE', 'SAASU_AGENT_MAY_WRITE', sprintf(
    'file %s will get accounts, two items, a contact, four USD sales and a payment, then be swept',
    $fileId
));

$saasu = harness_client($io, 150);

if (!$live) {
    harness_spent($io, $saasu);

    exit(0);
}

$stamp = date('Ymd-His');
$today = new DateTimeImmutable('now', new DateTimeZone('Australia/Sydney'));
$failures = 0;
$leaks = [];

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

// One USD item sale, read back: everything the five questions compare.
$sell = static function (Client $saasu, int $contact, string $summary, InvoiceLineItemRequest $line) use ($today): Invoice {
    $id = (int) $saasu->invoices()->create(
        InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Item, $today)
            ->withDueDate($today)
            ->withBillingContactId($contact)
            ->withAutoNumber()
            ->withTaxInclusive(true)
            ->withSummary($summary)
            ->withPurchaseOrderNumber('ZZPP' . strtoupper(bin2hex(random_bytes(6))))
            ->inCurrency('USD')
            ->addLineItem($line)
    )->id;

    return $saasu->invoices()->get($id);
};

$describe = static function (Invoice $invoice): array {
    $line = $invoice->lineItems[0] ?? null;

    return [
        'currency, rate, automatic?' => sprintf('%s, %s, %s', var_export($invoice->currency, true), var_export($invoice->fxRate, true), var_export($invoice->autoPopulateFxRate, true)),
        'total / tax / owed' => sprintf('%s / %s / %s', var_export($invoice->totalAmount, true), var_export($invoice->totalTaxAmount, true), var_export($invoice->amountOwed, true)),
        'line account, tax code' => $line === null ? '(no line)' : sprintf('#%s, %s', var_export($line->accountId, true), var_export($line->taxCode, true)),
        'line description' => $line === null ? '(no line)' : var_export($line->description, true),
    ];
};

try {
    $base = $saasu->files()->identity()->currencyCode ?? 'AUD';

    $taxCodes = [];

    foreach ($saasu->taxCodes()->all() as $code) {
        $taxCodes[(string) $code->code] = $code;
    }

    $combined = array_values(array_filter(array_keys($taxCodes), static fn (string $code): bool => str_contains($code, ',')));

    $io->line();
    $io->info('context');
    $io->values([
        'G1' => isset($taxCodes['G1']) ? sprintf('#%d, rate %s', $taxCodes['G1']->id, var_export($taxCodes['G1']->rate, true)) : '(absent)',
        'G1,G3' => isset($taxCodes['G1,G3']) ? sprintf('#%d, rate %s', $taxCodes['G1,G3']->id, var_export($taxCodes['G1,G3']->rate, true)) : '(absent)',
        'combined codes' => implode('  ', $combined) ?: '(none)',
    ]);

    $g1 = isset($taxCodes['G1']) ? (int) $taxCodes['G1']->id : null;

    $account = static fn (string $name, AccountType $type): int => (int) $saasu->accounts()->create(AccountRequest::of("ZZ {$name} {$stamp}", $type, $base))->id;

    $fees = $account('Item Merchant Fees', AccountType::Expense);
    $incomeService = $account('Item Income Service', AccountType::Income);
    $incomeStock = $account('Item Income Stock', AccountType::Income);
    $asset = $account('Item Stock', AccountType::Asset);
    $cos = $account('Item Cost of Sales', AccountType::CostOfSales);

    $bank = (int) $saasu->accounts()->create(
        AccountRequest::of("ZZ Item PayPal USD {$stamp}", AccountType::Asset, 'USD')
            ->withBankAccount(true)->withBankAccountName('ZZ PayPal USD')->withBsb('062000')->withAccountNumber('55555555')
            ->withMerchantFeeAccountId($fees)
    )->id;

    $contact = (int) $saasu->contacts()->create(ContactRequest::person('ZZ Item Subscriber', $stamp)->asCustomer())->id;

    $service = (int) $saasu->items()->create(
        ItemRequest::inventory("ZZ-SUB-{$stamp}", 'ZZ Item description (the item)')
            ->withInventoried(false)
            ->sold($incomeService, 110.00, $g1)
    )->id;

    $stock = (int) $saasu->items()->create(
        ItemRequest::inventory("ZZ-STK-{$stamp}", 'ZZ Stocked item (the item)')
            ->withInventoried(true)
            ->withAssetAccountId($asset)
            ->withSaleCostOfSalesAccountId($cos)
            ->sold($incomeStock, 110.00, $g1)
    )->id;

    $io->values([
        'income accounts' => "non-inventoried item #{$incomeService}, inventoried item #{$incomeStock}",
        'items' => "non-inventoried #{$service}, inventoried #{$stock} (no stock on hand)",
        'item sale tax code' => var_export($saasu->items()->get($service)->saleTaxCodeId, true) . ', sent ' . var_export($g1, true),
    ]);

    // ---- 1 and 5 -------------------------------------------------------------------------------
    $sale = $step('1, 5. non-inventoried item, G1, USD 110.00 inclusive, a line description of its own', static function () use ($saasu, $contact, $stamp, $service, $sell, $describe, $today, $io): int {
        $invoice = $sell($saasu, $contact, "ZZ Item sale {$stamp}", InvoiceLineItemRequest::item($service, 1, 110.00, 'G1', 'ZZ Line description (the line)'));
        $io->values(['number' => var_export($invoice->invoiceNumber, true)] + $describe($invoice));

        // Accrual basis, today only: this sale is the only income in the file.
        $day = $today->format('Y-m-d');
        $report = $saasu->reports()->profitAndLoss(ReportFilter::all()->period(DateRange::between($day, $day)));
        $io->values(['P&L income, by account' => implode('; ', array_map(
            static fn ($line): string => sprintf('#%s %s: %s', var_export($line->accountId, true), $line->accountName, var_export($line->amount, true)),
            $report->incomes,
        )) ?: '(none)']);

        return (int) $invoice->id;
    });

    if ($sale !== null) {
        $step('1. a USD payment of 110.00 with a 3.30 fee, into the USD bank account', static function () use ($saasu, $today, $bank, $sale, $io): void {
            $id = (int) $saasu->payments()->create(
                PaymentRequest::received($today, $bank, [PaymentItemRequest::of($sale, 110.00)])
                    ->inCurrency('USD')
                    ->withFeeAmount(3.30)
                    ->withReference('ZZPP' . strtoupper(bin2hex(random_bytes(6))))
                    ->withSummary('ZZ PayPal item sale payment')
            )->id;

            $read = $saasu->payments()->get($id);
            $invoice = $saasu->invoices()->get($sale);

            $balance = '(absent)';

            foreach ($saasu->accounts()->bankBalances() as $row) {
                if ($row->accountId === $bank) {
                    $balance = var_export($row->balance, true);
                }
            }

            $io->values([
                'payment currency, rate' => sprintf('%s, %s', var_export($read->currency, true), var_export($read->fxRate, true)),
                'total, fee' => sprintf('%s, %s', var_export($read->totalAmount, true), var_export($read->feeAmount, true)),
                'invoice owed, status' => sprintf('%s, %s', var_export($invoice->amountOwed, true), var_export($invoice->paymentStatus, true)),
                'bank balance after' => $balance,
            ]);
        });
    }

    // ---- 2 -------------------------------------------------------------------------------------
    $step('2. non-inventoried item, NO tax code on the line (the item has G1)', static function () use ($saasu, $contact, $stamp, $service, $sell, $describe, $io): void {
        $io->values($describe($sell($saasu, $contact, "ZZ Item sale no tax code {$stamp}", InvoiceLineItemRequest::item($service, 1, 110.00, null, 'ZZ No tax code'))));
    });

    // ---- 3 -------------------------------------------------------------------------------------
    $step('3. non-inventoried item, tax code "G1,G3"', static function () use ($saasu, $contact, $stamp, $service, $sell, $describe, $io): void {
        $io->values($describe($sell($saasu, $contact, "ZZ Item sale combined code {$stamp}", InvoiceLineItemRequest::item($service, 1, 110.00, 'G1,G3', 'ZZ Combined code'))));
    });

    // ---- 4 -------------------------------------------------------------------------------------
    $step('4. INVENTORIED item with no stock on hand, G1', static function () use ($saasu, $contact, $stamp, $stock, $sell, $describe, $io): void {
        // A refusal is an answer here, not a failure of the run.
        try {
            $invoice = $sell($saasu, $contact, "ZZ Item sale stocked {$stamp}", InvoiceLineItemRequest::item($stock, 1, 110.00, 'G1', 'ZZ Stocked'));
        } catch (ValidationException $e) {
            $io->values(['refused' => $e->error?->messages[0] ?? $e->getMessage()]);

            return;
        }

        $item = $saasu->items()->get($stock);

        $io->values($describe($invoice) + [
            'stock on hand after' => var_export($item->stockOnHand, true),
            'current value after' => var_export($item->currentValue, true),
        ]);
    });
} catch (ExceptionInterface|RuntimeException $e) {
    $failures++;
    $io->error($e::class . ': ' . $e->getMessage());

    if ($e instanceof ApiException && $e->body !== '') {
        $io->line('  raw body: ' . substr(str_replace(["\r", "\n"], ' ', $e->body), 0, 400));
    }
} finally {
    $io->line();
    $io->info('cleanup: sweeping every ZZ record');

    $leaks = harness_sweep($saasu, $io);

    harness_spent($io, $saasu);
}

if ($leaks !== [] || $failures > 0) {
    exit(1);
}

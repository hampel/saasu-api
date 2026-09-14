<?php

/**
 * Exercise: multi-currency - raise invoices in foreign currencies, and change the currency of one
 * already raised in the base currency. WRITES. Refuses any file but SAASU_DISPOSABLE_FILE_ID.
 *
 * Read-only by default. Needs SAASU_WRITE=1, and in an agent session SAASU_AGENT_MAY_WRITE=1 on
 * the command line. The file needs multi-currency turned on. Sends no email. About 60 requests.
 *
 * THE CASE IT EXISTS FOR: a sale raised in the file's base currency by a system that cannot choose
 * a currency - a scheduled or recurring sale - which should have been in another. Saasu has no
 * currency on a contact and no recurring-sale endpoint, so a fix has to find such an invoice and
 * change its currency after the fact. Whether the API allows that, and what it does to the
 * amounts, the tax and the rate, is what this settles:
 *
 *   1. CAN AN EXISTING INVOICE'S CURRENCY BE CHANGED BY A PUT? With the rate filled in by Saasu,
 *      and with a rate given by hand. Do the line amounts stay as numbers (110 AUD becomes
 *      110 USD) or are they converted? Does the invoice number survive? Does a GST tax code?
 *   2. DOES A PAYMENT PREVENT IT? A part-paid invoice is the likeliest thing to have to fix.
 *   3. WHAT RATE DOES SAASU FILL IN - today's, or the invoice date's?
 *   4. HOW IS A FOREIGN-CURRENCY PAYMENT RECORDED, into a bank account of that currency and into
 *      one of the base currency?
 *
 * Cleanup is the shared ZZ sweep, in a `finally`.
 *
 * @var Hampel\Rig\Io $io
 */

use Hampel\Saasu\Api\Entity\Invoice;
use Hampel\Saasu\Api\Enum\AccountType;
use Hampel\Saasu\Api\Enum\InvoiceLayout;
use Hampel\Saasu\Api\Enum\InvoiceType;
use Hampel\Saasu\Api\Exception\ApiException;
use Hampel\Saasu\Api\Exception\ExceptionInterface;
use Hampel\Saasu\Api\Request\AccountRequest;
use Hampel\Saasu\Api\Request\ContactRequest;
use Hampel\Saasu\Api\Request\InvoiceLineItemRequest;
use Hampel\Saasu\Api\Request\InvoiceRequest;
use Hampel\Saasu\Api\Request\PaymentItemRequest;
use Hampel\Saasu\Api\Request\PaymentRequest;

require __DIR__ . '/lib/harness.php';
require __DIR__ . '/lib/sweep.php';

$io->title('saasu · currency write');

$fileId = harness_require_disposable_file($io);

$live = harness_mode($io, 'SAASU_WRITE', 'SAASU_AGENT_MAY_WRITE', sprintf(
    'file %s will get foreign-currency invoices and payments, then be swept',
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

$show = static function (string $label, Invoice $invoice) use ($io): void {
    $line = $invoice->lineItems[0] ?? null;

    $io->values([$label => sprintf(
        'number %s, %s, FxRate %s, total %s, tax %s, owed %s, line %s at %s',
        $invoice->invoiceNumber,
        $invoice->currency,
        var_export($invoice->fxRate, true),
        var_export($invoice->totalAmount, true),
        var_export($invoice->totalTaxAmount, true),
        var_export($invoice->amountOwed, true),
        var_export($line?->totalAmount, true),
        $line?->taxCode ?? '(no tax code)',
    )]);
};

/**
 * A sale in the base currency, as a system that cannot choose a currency would raise it.
 */
$baseSale = static function (int $contactId, int $incomeId, string $summary, ?DateTimeInterface $date = null) use ($saasu, $today): int {
    return (int) $saasu->invoices()->create(
        InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, $date ?? $today)
            ->withBillingContactId($contactId)
            ->withAutoNumber()
            ->withTaxInclusive(true)
            ->withSummary($summary)
            ->addLineItem(InvoiceLineItemRequest::service('Subscription', $incomeId, 110.00, 'G1'))
    )->id;
};

try {
    $ids = $step('setup: a customer, an income account, AUD and USD bank accounts', static function () use ($saasu, $stamp, $io): array {
        $base = $saasu->files()->identity()->currencyCode ?? 'AUD';

        $ids = [
            'contact' => (int) $saasu->contacts()->create(ContactRequest::person('ZZ Currency customer', $stamp)->asCustomer())->id,
            'income' => (int) $saasu->accounts()->create(AccountRequest::of("ZZ Currency Income {$stamp}", AccountType::Income, $base))->id,
            'bankBase' => (int) $saasu->accounts()->create(AccountRequest::of("ZZ Currency Bank {$base} {$stamp}", AccountType::Asset, $base)->withBankAccount(true)->withBankAccountName('ZZ AUD')->withBsb('062000')->withAccountNumber('11111111'))->id,
            'bankUsd' => (int) $saasu->accounts()->create(AccountRequest::of("ZZ Currency Bank USD {$stamp}", AccountType::Asset, 'USD')->withBankAccount(true)->withBankAccountName('ZZ USD')->withBsb('062000')->withAccountNumber('22222222'))->id,
        ];

        $io->values(['base currency' => $base, 'USD bank account currency' => $saasu->accounts()->get($ids['bankUsd'])->currency]);

        return $ids;
    });

    if (!is_array($ids)) {
        throw new RuntimeException('setup failed, so nothing else can run');
    }

    $step('1. change an existing base-currency sale to USD, rate filled in by Saasu', static function () use ($saasu, $stamp, $ids, $show, $baseSale, $io): void {
        $id = $baseSale($ids['contact'], $ids['income'], "ZZ Currency scheduled sale {$stamp}");
        $before = $saasu->invoices()->get($id);
        $show('as raised', $before);

        $saasu->invoices()->update($id, InvoiceRequest::from($before)->inCurrency('USD'));
        $usd = $saasu->invoices()->get($id);
        $show('after inCurrency(USD)', $usd);

        $io->values([
            'invoice number kept' => $usd->invoiceNumber === $before->invoiceNumber ? 'yes' : 'NO',
            'amounts converted' => abs(($usd->totalAmount ?? 0) - ($before->totalAmount ?? 0)) < 0.005 ? 'no - the numbers stayed, the currency changed' : 'YES - Saasu converted them',
        ]);

        $io->line();
        $io->info('   then to EUR with a rate given by hand, then back to the base currency');

        $saasu->invoices()->update($id, InvoiceRequest::from($usd)->inCurrency('EUR', 1.6));
        $eur = $saasu->invoices()->get($id);
        $show('after inCurrency(EUR, 1.6)', $eur);

        $saasu->invoices()->update($id, InvoiceRequest::from($eur)->inCurrency((string) $before->currency));
        $show('after PUT back', $saasu->invoices()->get($id));

        $pdf = $saasu->invoices()->pdf($id);
        $io->values(['PDF after the changes' => $pdf->isPdf() ? sprintf('a PDF, %d bytes', $pdf->size()) : 'NOT a PDF']);
    });

    $step('1b. the smallest PUT that changes currency: only Currency differs from the record', static function () use ($saasu, $stamp, $ids, $show, $baseSale): void {
        $id = $baseSale($ids['contact'], $ids['income'], "ZZ Currency minimal change {$stamp}");
        $before = $saasu->invoices()->get($id);

        // The trap InvoiceRequest::inCurrency() exists for: withCurrency() alone. The record as read
        // says AutoPopulateFxRate false and FxRate 1 for a base-currency sale, and a PUT that
        // changes only the currency keeps both - measured 2026-09-14, USD at a rate of 1.0.
        $saasu->invoices()->update($id, InvoiceRequest::from($before)->withCurrency('USD'));
        $show('Currency only', $saasu->invoices()->get($id));
    });

    $step('2. a part-paid base-currency sale: can its currency still change?', static function () use ($saasu, $stamp, $ids, $show, $baseSale, $today, $io): void {
        $id = $baseSale($ids['contact'], $ids['income'], "ZZ Currency part-paid {$stamp}");
        $saasu->payments()->create(PaymentRequest::received($today, $ids['bankBase'], [PaymentItemRequest::of($id, 50.00)]));
        $before = $saasu->invoices()->get($id);
        $show('part-paid', $before);

        try {
            $saasu->invoices()->update($id, InvoiceRequest::from($before)->inCurrency('USD'));
            $show('ACCEPTED - after PUT to USD', $saasu->invoices()->get($id));
        } catch (ApiException $e) {
            $io->warn(sprintf('  refused (HTTP %d): %s', $e->statusCode, $e->getMessage()));
        }
    });

    $step('3. which rate does Saasu fill in - today\'s, or the invoice date\'s?', static function () use ($saasu, $stamp, $ids, $today, $io): void {
        $rates = [];

        foreach (['today' => $today, '90 days ago' => $today->modify('-90 days'), 'a year ago' => $today->modify('-1 year')] as $label => $date) {
            $id = (int) $saasu->invoices()->create(
                InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, $date)
                    ->withBillingContactId($ids['contact'])
                    ->withAutoNumber()
                    ->withCurrency('USD')
                    ->withAutoPopulateFxRate(true)
                    ->withSummary("ZZ Currency rate {$label} {$stamp}")
                    ->addLineItem(InvoiceLineItemRequest::service('Subscription', $ids['income'], 100.00))
            )->id;
            $rates[$label] = $saasu->invoices()->get($id)->fxRate;
        }

        $io->values(array_map(static fn ($rate): string => var_export($rate, true), $rates));

        count(array_unique(array_map('strval', $rates))) > 1
            ? $io->success('  the rate follows the invoice date')
            : $io->warn('  the same rate for every date - Saasu fills in the current rate, whatever the invoice is dated');
    });

    $step('4. foreign-currency payments', static function () use ($saasu, $stamp, $ids, $show, $today, $io): void {
        foreach (['into the USD bank account' => $ids['bankUsd'], 'into the base-currency bank account' => $ids['bankBase']] as $label => $bank) {
            $id = (int) $saasu->invoices()->create(
                InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, $today)
                    ->withBillingContactId($ids['contact'])
                    ->withAutoNumber()
                    ->withCurrency('USD')
                    ->withAutoPopulateFxRate(true)
                    ->withSummary("ZZ Currency payment {$label} {$stamp}")
                    ->addLineItem(InvoiceLineItemRequest::service('Subscription', $ids['income'], 100.00))
            )->id;

            try {
                $saved = $saasu->payments()->create(
                    PaymentRequest::received($today, $bank, [PaymentItemRequest::of($id, 100.00)])
                        ->withCurrency('USD')
                        ->withAutoPopulateFxRate(true)
                );
                $payment = $saasu->payments()->get((int) $saved->id);
                $io->values([$label => sprintf('accepted: %s %s at FxRate %s', $payment->currency, var_export($payment->totalAmount, true), var_export($payment->fxRate, true))]);
                $show('   invoice after', $saasu->invoices()->get($id));
            } catch (ApiException $e) {
                $io->warn(sprintf('  %s refused (HTTP %d): %s', $label, $e->statusCode, $e->getMessage()));
            }
        }
    });

    $step('the invoice list carries the currency, so a fix can find what to change', static function () use ($saasu, $io): void {
        $currencies = [];

        foreach ($saasu->invoices()->all() as $invoice) {
            if (str_starts_with((string) $invoice->summary, 'ZZ Currency')) {
                $currencies[] = (string) $invoice->currency;
            }
        }

        $io->values(['currencies on the ZZ invoices, from the list alone' => json_encode(array_count_values($currencies)) ?: '{}']);
    });
} finally {
    $io->line();
    $io->info('cleanup: sweeping every ZZ record');

    $leaks = harness_sweep($saasu, $io);

    harness_spent($io, $saasu);
}

if ($leaks !== [] || $failures > 0) {
    exit(1);
}

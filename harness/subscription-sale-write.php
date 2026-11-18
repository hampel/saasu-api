<?php

/**
 * Exercise: a subscription paid through PayPal in USD, booked from nothing - a new USD sale, then a
 * USD payment with a fee into a USD bank account. WRITES. Refuses any file but
 * SAASU_DISPOSABLE_FILE_ID. Sends no email. A clean run spends about 41 requests.
 *
 * Read-only by default. Needs SAASU_WRITE=1, and in an agent session SAASU_AGENT_MAY_WRITE=1 on
 * the command line. The file needs multi-currency on.
 *
 * Written for a tool that creates each period's sale from the PayPal payment, rather than
 * converting an AUD invoice a recurring sale raised. THE QUESTIONS:
 *
 *   1. DOES ContactFilter::withEmail() MATCH AN ADDITIONAL EMAIL, or only the primary one - and does
 *      it ignore case? A payer's PayPal address is often not the one on the contact.
 *   2. DOES A NEW SALE CREATED IN USD with an automatic rate get one, and does a USD payment with a
 *      fee into a USD bank account pay it in full? workflows-write measured an AUD invoice moved to
 *      USD; this is the same end state reached from an insert.
 *   3. MAY A USD BANK ACCOUNT HAVE A BASE-CURRENCY MERCHANT FEE ACCOUNT, and is it read back by
 *      accounts()->get() and in the account list? A tool checking its configuration needs both.
 *   4. CAN THE PAYPAL TRANSACTION ID BE FOUND AGAIN cheaply, for a second run to skip it? There is no
 *      payment filter on Reference, so: does a payment's reference come back in a list row, and is
 *      the sale's PurchaseOrderNumber filter honoured on a sale?
 *
 * Cleanup is the shared ZZ sweep, in a `finally`.
 *
 * @var Hampel\Rig\Io $io
 */

use Hampel\Saasu\Api\Client;
use Hampel\Saasu\Api\Enum\AccountType;
use Hampel\Saasu\Api\Enum\InvoiceLayout;
use Hampel\Saasu\Api\Enum\InvoiceType;
use Hampel\Saasu\Api\Exception\ApiException;
use Hampel\Saasu\Api\Exception\ExceptionInterface;
use Hampel\Saasu\Api\Filter\ContactFilter;
use Hampel\Saasu\Api\Filter\DateRange;
use Hampel\Saasu\Api\Filter\InvoiceFilter;
use Hampel\Saasu\Api\Filter\PaymentFilter;
use Hampel\Saasu\Api\Request\AccountRequest;
use Hampel\Saasu\Api\Request\ContactRequest;
use Hampel\Saasu\Api\Request\InvoiceLineItemRequest;
use Hampel\Saasu\Api\Request\InvoiceRequest;
use Hampel\Saasu\Api\Request\PaymentItemRequest;
use Hampel\Saasu\Api\Request\PaymentRequest;

require __DIR__ . '/lib/harness.php';
require __DIR__ . '/lib/sweep.php';

$io->title('saasu · subscription sale write');

$fileId = harness_require_disposable_file($io);

$live = harness_mode($io, 'SAASU_WRITE', 'SAASU_AGENT_MAY_WRITE', sprintf(
    'file %s will get a USD bank account, a contact, a USD sale and its payment, then be swept',
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

$balance = static function (Client $saasu, int $accountId): string {
    foreach ($saasu->accounts()->bankBalances() as $balance) {
        if ($balance->accountId === $accountId) {
            return var_export($balance->balance, true);
        }
    }

    return '(absent - no transactions yet)';
};

try {
    $base = $saasu->files()->identity()->currencyCode ?? 'AUD';
    $fees = (int) $saasu->accounts()->create(AccountRequest::of("ZZ Sub Merchant Fees {$stamp}", AccountType::Expense, $base))->id;
    $income = (int) $saasu->accounts()->create(AccountRequest::of("ZZ Sub Income {$stamp}", AccountType::Income, $base))->id;

    // ---- 3 -------------------------------------------------------------------------------------
    $bank = $step("3. a USD bank account with a {$base} merchant fee account", static function () use ($saasu, $stamp, $fees, $io): int {
        $id = (int) $saasu->accounts()->create(
            AccountRequest::of("ZZ Sub PayPal USD {$stamp}", AccountType::Asset, 'USD')
                ->withBankAccount(true)->withBankAccountName('ZZ PayPal USD')->withBsb('062000')->withAccountNumber('55555555')
                ->withMerchantFeeAccountId($fees)
        )->id;

        $listed = null;

        foreach ($saasu->accounts()->all() as $account) {
            if ($account->id === $id) {
                $listed = $account;
            }
        }

        $io->values([
            'created' => "#{$id}, merchant fee account sent #{$fees}",
            'get() reads back' => var_export($saasu->accounts()->get($id)->merchantFeeAccountId, true),
            'list row reads back' => $listed === null ? '(not in the list)' : var_export($listed->merchantFeeAccountId, true),
        ]);

        return $id;
    });

    // ---- 1 -------------------------------------------------------------------------------------
    $contact = $step('1. finding a contact by email', static function () use ($saasu, $stamp, $io): int {
        $primary = "zz-primary-{$stamp}@example.invalid";
        $alternate = "zz-alternate-{$stamp}@example.invalid";

        $id = (int) $saasu->contacts()->create(
            ContactRequest::person('ZZ Subscriber', $stamp)->asCustomer()
                ->withEmailAddress($primary)
                ->withAdditionalEmailList([$alternate])
        )->id;

        $found = static fn (string $email): string => (string) count($saasu->contacts()->list(ContactFilter::all()->withEmail($email)));

        $io->values([
            'primary' => $found($primary),
            'primary, upper case' => $found(strtoupper($primary)),
            'additional' => $found($alternate),
            'additional reads back as' => var_export($saasu->contacts()->get($id)->additionalEmails, true),
        ]);

        return $id;
    });

    // ---- 2 -------------------------------------------------------------------------------------
    $txn = 'ZZPP' . strtoupper(bin2hex(random_bytes(6)));

    $sale = $contact === null ? null : $step('2a. a new sale, in USD from the start, automatic rate', static function () use ($saasu, $today, $contact, $income, $stamp, $txn, $io): int {
        $id = (int) $saasu->invoices()->create(
            InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, $today)
                ->withBillingContactId($contact)
                ->withAutoNumber()
                ->withTaxInclusive(true)
                ->withSummary("ZZ Subscription {$stamp}")
                ->withPurchaseOrderNumber($txn)
                ->inCurrency('USD')
                ->addLineItem(InvoiceLineItemRequest::service('Subscription', $income, 30.00))
        )->id;

        $invoice = $saasu->invoices()->get($id);

        $io->values([
            'number' => var_export($invoice->invoiceNumber, true),
            'currency' => var_export($invoice->currency, true),
            'rate, automatic?' => sprintf('%s, %s', var_export($invoice->fxRate, true), var_export($invoice->autoPopulateFxRate, true)),
            'total / owed' => sprintf('%s / %s', var_export($invoice->totalAmount, true), var_export($invoice->amountOwed, true)),
            'status' => var_export($invoice->paymentStatus, true),
        ]);

        return $id;
    });

    $payment = ($sale === null || $bank === null) ? null : $step('2b. a USD payment of 30.00 with a 1.47 fee, into the USD bank account', static function () use ($saasu, $today, $bank, $sale, $txn, $balance, $io): int {
        $before = $balance($saasu, $bank);

        $id = (int) $saasu->payments()->create(
            PaymentRequest::received($today, $bank, [PaymentItemRequest::of($sale, 30.00)])
                ->inCurrency('USD')
                ->withFeeAmount(1.47)
                ->withReference($txn)
                ->withSummary('ZZ PayPal subscription payment')
        )->id;

        $read = $saasu->payments()->get($id);
        $invoice = $saasu->invoices()->get($sale);

        $io->values([
            'payment currency, rate' => sprintf('%s, %s', var_export($read->currency, true), var_export($read->fxRate, true)),
            'total, fee' => sprintf('%s, %s', var_export($read->totalAmount, true), var_export($read->feeAmount, true)),
            'invoice owed, status' => sprintf('%s, %s', var_export($invoice->amountOwed, true), var_export($invoice->paymentStatus, true)),
            'bank balance before' => $before,
            'bank balance after' => $balance($saasu, $bank),
        ]);

        return $id;
    });

    // ---- 4 -------------------------------------------------------------------------------------
    if ($payment !== null && $bank !== null) {
        $step('4. finding the PayPal transaction id again', static function () use ($saasu, $today, $bank, $txn, $io): void {
            $window = DateRange::between($today->modify('-3 days')->format('Y-m-d'), $today->modify('+3 days')->format('Y-m-d'));
            $rows = $saasu->payments()->list(PaymentFilter::all()->intoAccount($bank)->paidBetween($window));

            // The key's presence and its value, beside the full record's - a list row can carry the
            // key as null while the record holds a value, and that reads as "no reference".
            $references = array_map(static fn ($row): string => sprintf(
                'list %s / record %s',
                array_key_exists('Reference', $row->raw) ? var_export($row->raw['Reference'], true) : '(no key)',
                var_export($saasu->payments()->get((int) $row->id)->reference, true),
            ), $rows->items);

            $io->values([
                'payments into the account' => (string) count($rows),
                'Reference, list row / full record' => implode('; ', $references) ?: '(none)',
                'sales, PurchaseOrderNumber=<txn>' => (string) count($saasu->invoices()->list(InvoiceFilter::all()->purchaseOrderNumber($txn))),
                'sales, PurchaseOrderNumber=<other>' => (string) count($saasu->invoices()->list(InvoiceFilter::all()->purchaseOrderNumber('ZZPPNOSUCH' . bin2hex(random_bytes(3))))),
            ]);
        });
    }
} catch (ExceptionInterface|RuntimeException $e) {
    $failures++;
    $io->error($e::class . ': ' . $e->getMessage());
} finally {
    $io->line();
    $io->info('cleanup: sweeping every ZZ record');

    $leaks = harness_sweep($saasu, $io);

    harness_spent($io, $saasu);
}

if ($leaks !== [] || $failures > 0) {
    exit(1);
}

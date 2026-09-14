<?php

/**
 * Exercise: three bookkeeping jobs done by hand today, done through the API. WRITES. Refuses any
 * file but SAASU_DISPOSABLE_FILE_ID. Sends no email. About 90 requests.
 *
 * Read-only by default. Needs SAASU_WRITE=1, and in an agent session SAASU_AGENT_MAY_WRITE=1 on
 * the command line. The file needs multi-currency on.
 *
 * THE JOBS, and the question each one turns on:
 *
 *   1. A RECURRING SALE RAISED UNPAID IN AUD THAT SHOULD HAVE BEEN USD: move it to USD, then record
 *      a USD payment at Saasu's own rate for the payment date. Does the invoice end up fully paid,
 *      and what does the bank account receive in AUD - including when the payment is dated after
 *      the invoice, so the two rates differ?
 *   2. A CARD OR PAYPAL SETTLEMENT WITH A MERCHANT FEE: the invoice is paid in full, the bank
 *      receives less by the fee. Saasu documents `FeeAmount` as needing a merchant fee account on
 *      the bank account. Does a fee added to a new payment - and to an existing one - leave the
 *      invoice paid and the bank short by the fee? What is refused without the fee account?
 *   3. A FOREIGN-CURRENCY PURCHASE PAID FROM AN AUD ACCOUNT, where the AUD figure is what the bank
 *      statement says. The API takes a payment's rate, not its AUD amount, so the rate has to be
 *      AUD divided by the foreign amount. Does that book exactly the statement figure, to the cent,
 *      for awkward amounts? And how precisely does Saasu keep the rate?
 *
 * "What the bank received" is read from the bank account's balance before and after, which is the
 * figure a reconciliation would compare with the statement.
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
use Hampel\Saasu\Api\Request\AccountRequest;
use Hampel\Saasu\Api\Request\ContactRequest;
use Hampel\Saasu\Api\Request\InvoiceLineItemRequest;
use Hampel\Saasu\Api\Request\InvoiceRequest;
use Hampel\Saasu\Api\Request\PaymentItemRequest;
use Hampel\Saasu\Api\Request\PaymentRequest;

require __DIR__ . '/lib/harness.php';
require __DIR__ . '/lib/sweep.php';

$io->title('saasu · workflows write');

$fileId = harness_require_disposable_file($io);

$live = harness_mode($io, 'SAASU_WRITE', 'SAASU_AGENT_MAY_WRITE', sprintf(
    'file %s will get sales, purchases, payments and fees, then be swept',
    $fileId
));

$saasu = harness_client($io, 200);

if (!$live) {
    harness_spent($io, $saasu);

    exit(0);
}

$stamp = date('Ymd-His');
$today = new DateTimeImmutable('now', new DateTimeZone('Australia/Sydney'));
$failures = 0;
$leaks = [];

$step = static function (string $label, callable $probe) use ($io, &$failures): void {
    $io->line();
    $io->info($label);

    try {
        $probe();
    } catch (ExceptionInterface|RuntimeException|TypeError $e) {
        $failures++;
        $io->error('  ' . $e::class . ': ' . $e->getMessage());

        if ($e instanceof ApiException && $e->body !== '') {
            $io->line('  raw body: ' . substr(str_replace(["\r", "\n"], ' ', $e->body), 0, 400));
        }
    }
};

$balance = static function (Client $saasu, int $accountId): float {
    foreach ($saasu->accounts()->bankBalances() as $balance) {
        if ($balance->accountId === $accountId) {
            return (float) $balance->balance;
        }
    }

    // Measured 2026-09-14: a bank account with no transactions yet is absent from the list, not
    // listed at zero.
    return 0.0;
};

$money = static fn (?float $value): string => $value === null ? '(null)' : number_format($value, 2, '.', '');

try {
    $base = $saasu->files()->identity()->currencyCode ?? 'AUD';

    $expense = (int) $saasu->accounts()->create(AccountRequest::of("ZZ Workflow Expense {$stamp}", AccountType::Expense, $base))->id;
    $fees = (int) $saasu->accounts()->create(AccountRequest::of("ZZ Workflow Merchant Fees {$stamp}", AccountType::Expense, $base))->id;
    $income = (int) $saasu->accounts()->create(AccountRequest::of("ZZ Workflow Income {$stamp}", AccountType::Income, $base))->id;
    $bank = (int) $saasu->accounts()->create(
        AccountRequest::of("ZZ Workflow Bank {$stamp}", AccountType::Asset, $base)
            ->withBankAccount(true)->withBankAccountName('ZZ Workflow')->withBsb('062000')->withAccountNumber('33333333')
    )->id;
    $gateway = (int) $saasu->accounts()->create(
        AccountRequest::of("ZZ Workflow PayPal {$stamp}", AccountType::Asset, $base)
            ->withBankAccount(true)->withBankAccountName('ZZ PayPal')->withBsb('062000')->withAccountNumber('44444444')
            ->withMerchantFeeAccountId($fees)
    )->id;
    $customer = (int) $saasu->contacts()->create(ContactRequest::person('ZZ Workflow customer', $stamp)->asCustomer())->id;
    $supplier = (int) $saasu->contacts()->create(ContactRequest::person('ZZ Workflow supplier', $stamp)->asSupplier())->id;

    $io->values([
        'setup' => sprintf('base %s; bank #%d; PayPal-style account #%d with merchant fee account #%d', $base, $bank, $gateway, $fees),
        'merchant fee account read back' => var_export($saasu->accounts()->get($gateway)->merchantFeeAccountId, true),
    ]);

    $sale = static function (string $summary, float $amount, DateTimeInterface $date) use ($saasu, $customer, $income): int {
        return (int) $saasu->invoices()->create(
            InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, $date)
                ->withBillingContactId($customer)
                ->withAutoNumber()
                ->withTaxInclusive(true)
                ->withSummary($summary)
                ->addLineItem(InvoiceLineItemRequest::service('Subscription', $income, $amount, 'G1'))
        )->id;
    };

    // ---- 1 -----------------------------------------------------------------------------------

    foreach (['paid the same day' => $today, 'raised 30 days before it was paid' => $today->modify('-30 days')] as $label => $raised) {
        $step("1. recurring AUD sale moved to USD and paid in USD - {$label}", static function () use ($saasu, $stamp, $sale, $bank, $balance, $money, $today, $raised, $label, $io): void {
            $id = $sale("ZZ Workflow recurring {$label} {$stamp}", 110.00, $raised);
            $saasu->invoices()->update($id, InvoiceRequest::from($saasu->invoices()->get($id))->inCurrency('USD'));
            $invoice = $saasu->invoices()->get($id);

            $before = $balance($saasu, $bank);
            $saved = $saasu->payments()->create(
                PaymentRequest::received($today, $bank, [PaymentItemRequest::of($id, 110.00)])->inCurrency('USD')
            );
            $after = $balance($saasu, $bank);
            $payment = $saasu->payments()->get((int) $saved->id);
            $paidInvoice = $saasu->invoices()->get($id);

            $io->values([
                'invoice' => sprintf('USD %s at FxRate %s (invoice date)', $money($invoice->totalAmount), var_export($invoice->fxRate, true)),
                'payment' => sprintf('%s %s at FxRate %s (payment date)', $payment->currency, $money($payment->totalAmount), var_export($payment->fxRate, true)),
                'invoice owed after' => $money($paidInvoice->amountOwed),
                'bank received, AUD' => $money($after - $before),
                'USD x payment rate' => $money(round(110.00 * (float) $payment->fxRate, 2)),
            ]);
        });
    }

    // ---- 2 -----------------------------------------------------------------------------------

    $step('2a. a new payment with a merchant fee, into an account with a fee account', static function () use ($saasu, $stamp, $sale, $gateway, $balance, $money, $today, $io, $fees): void {
        $id = $sale("ZZ Workflow card sale fee on insert {$stamp}", 110.00, $today);

        $before = $balance($saasu, $gateway);
        $saved = $saasu->payments()->create(
            PaymentRequest::received($today, $gateway, [PaymentItemRequest::of($id, 110.00)])->withFeeAmount(3.30)
        );
        $after = $balance($saasu, $gateway);
        $payment = $saasu->payments()->get((int) $saved->id);

        $io->values([
            'payment' => sprintf('TotalAmount %s, FeeAmount %s, applied %s', $money($payment->totalAmount), $money($payment->feeAmount), $money($payment->paymentItems[0]->amountPaid ?? null)),
            'invoice owed after' => $money($saasu->invoices()->get($id)->amountOwed),
            'account received' => $money($after - $before),
        ]);
    });

    $step('2b. an existing payment, then the fee added to it', static function () use ($saasu, $stamp, $sale, $gateway, $balance, $money, $today, $io): void {
        $id = $sale("ZZ Workflow card sale fee added later {$stamp}", 110.00, $today);
        $saved = $saasu->payments()->create(PaymentRequest::received($today, $gateway, [PaymentItemRequest::of($id, 110.00)]));

        $before = $balance($saasu, $gateway);
        $payment = $saasu->payments()->get((int) $saved->id);
        $saasu->payments()->update((int) $saved->id, PaymentRequest::from($payment)->withFeeAmount(3.30));
        $after = $balance($saasu, $gateway);
        $adjusted = $saasu->payments()->get((int) $saved->id);

        $io->values([
            'payment after' => sprintf('TotalAmount %s, FeeAmount %s, applied %s', $money($adjusted->totalAmount), $money($adjusted->feeAmount), $money($adjusted->paymentItems[0]->amountPaid ?? null)),
            'invoice owed after' => $money($saasu->invoices()->get($id)->amountOwed),
            'account balance moved by' => $money($after - $before),
        ]);
    });

    $step('2c. a fee on a payment into an account with no merchant fee account', static function () use ($saasu, $stamp, $sale, $bank, $today, $io): void {
        $id = $sale("ZZ Workflow card sale no fee account {$stamp}", 110.00, $today);

        try {
            $saasu->payments()->create(PaymentRequest::received($today, $bank, [PaymentItemRequest::of($id, 110.00)])->withFeeAmount(3.30));
            $io->warn('  ACCEPTED without a merchant fee account');
        } catch (ApiException $e) {
            $io->success(sprintf('  refused (HTTP %d): %s', $e->statusCode, implode(' ', $e->messages())));
        }
    });

    $step('2d. a USD sale paid in USD into the fee account, fee in USD', static function () use ($saasu, $stamp, $sale, $gateway, $balance, $money, $today, $io): void {
        $id = $sale("ZZ Workflow USD card sale {$stamp}", 110.00, $today);
        $saasu->invoices()->update($id, InvoiceRequest::from($saasu->invoices()->get($id))->inCurrency('USD'));

        $before = $balance($saasu, $gateway);
        $saved = $saasu->payments()->create(
            PaymentRequest::received($today, $gateway, [PaymentItemRequest::of($id, 110.00)])->inCurrency('USD')->withFeeAmount(4.50)
        );
        $after = $balance($saasu, $gateway);
        $payment = $saasu->payments()->get((int) $saved->id);

        $io->values([
            'payment' => sprintf('%s TotalAmount %s, FeeAmount %s, FxRate %s', $payment->currency, $money($payment->totalAmount), $money($payment->feeAmount), var_export($payment->fxRate, true)),
            'invoice owed after' => $money($saasu->invoices()->get($id)->amountOwed),
            'account received, AUD' => $money($after - $before),
            '(110 - 4.50) x rate' => $money(round((110.00 - 4.50) * (float) $payment->fxRate, 2)),
        ]);
    });

    // ---- 3 -----------------------------------------------------------------------------------

    foreach ([[49.99, 72.37], [1234.56, 1901.07], [0.99, 1.53], [333.33, 499.99]] as [$foreign, $aud]) {
        $step(sprintf('3. a USD %s purchase, and the bank statement says AUD %s', $foreign, $aud), static function () use ($saasu, $stamp, $supplier, $expense, $bank, $balance, $money, $today, $foreign, $aud, $io): void {
            $id = (int) $saasu->invoices()->create(
                InvoiceRequest::purchase(InvoiceType::TaxInvoice, InvoiceLayout::Service, $today)
                    ->withBillingContactId($supplier)
                    ->withInvoiceNumber("ZZ-{$stamp}-" . str_replace('.', '', (string) $foreign))
                    ->withSummary("ZZ Workflow USD purchase {$foreign} {$stamp}")
                    ->inCurrency('USD')
                    ->addLineItem(InvoiceLineItemRequest::service('Software', $expense, $foreign))
            )->id;

            $request = PaymentRequest::paid($today, $bank, [PaymentItemRequest::of($id, $foreign)])->inCurrencyForBaseAmount('USD', $foreign, $aud);
            $rate = (float) $request->get('FxRate');
            $before = $balance($saasu, $bank);
            $saved = $saasu->payments()->create($request);
            $after = $balance($saasu, $bank);
            $payment = $saasu->payments()->get((int) $saved->id);
            $booked = round($before - $after, 2);

            $io->values([
                'rate sent' => sprintf('%.12f', $rate),
                'rate kept' => var_export($payment->fxRate, true),
                'bank paid out, AUD' => $money($booked),
                'invoice owed after' => $money($saasu->invoices()->get($id)->amountOwed),
            ]);

            abs($booked - $aud) < 0.005
                ? $io->success(sprintf('  the statement figure, to the cent: AUD %s', $money($aud)))
                : $io->warn(sprintf('  OFF BY %s - the rate cannot carry the statement figure exactly', $money($booked - $aud)));
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

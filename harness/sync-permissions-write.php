<?php

/**
 * Exercise: as a restricted user, make every call a PayPal-to-Saasu sync makes - the reads it checks
 * with, an item-layout USD sale and its payment with a fee - and report which the user's permissions
 * allow. WRITES. Refuses any file but SAASU_DISPOSABLE_FILE_ID. Sends no email. A clean run spends
 * about 45 requests.
 *
 * Read-only by default. Needs SAASU_WRITE=1, and in an agent session SAASU_AGENT_MAY_WRITE=1 on
 * the command line. The file needs multi-currency on.
 *
 * Run once per permission set: set the restricted user's permissions in Saasu's own screens, run,
 * read which calls were refused, widen, run again. The exercise cannot change permissions itself.
 *
 * THE QUESTIONS:
 *
 *   1. WHICH CALLS DOES THIS PERMISSION SET ALLOW? Each read and each write is made by the restricted
 *      user and reported as allowed, or refused with its exception, status and body.
 *   2. DOES AN ITEM-LAYOUT SALE NEED ITEM OR ACCOUNT PERMISSIONS beyond the sale itself? The sale
 *      names an item, and the item names an income account.
 *   3. DOES A PAYMENT NEED A BANKING OR ACCOUNTS PERMISSION beyond payments? The payment goes into a
 *      USD bank account with a merchant fee account.
 *   4. WHAT DOES A REFUSED WRITE LOOK LIKE? Only an account insert has been measured: 403, no body.
 *
 * The main login builds everything the restricted user needs - accounts, an item, a contact - so a
 * refusal there cannot hide a later answer; and when the restricted user is refused the sale, the
 * main login raises it so the payment is still asked. Cleanup is the shared ZZ sweep, in a
 * `finally`, by the main login.
 *
 * Needs SAASU_RESTRICTED_USERNAME and SAASU_RESTRICTED_PASSWORD beside the main login.
 *
 * @var Hampel\Rig\Io $io
 */

use Hampel\Saasu\Api\Authentication\OAuth;
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
use Hampel\Saasu\Api\Request\ItemRequest;
use Hampel\Saasu\Api\Request\PaymentItemRequest;
use Hampel\Saasu\Api\Request\PaymentRequest;

require __DIR__ . '/lib/harness.php';
require __DIR__ . '/lib/sweep.php';

$io->title('saasu · sync permissions write');

$restrictedUsername = getenv('SAASU_RESTRICTED_USERNAME') ?: '';
$restrictedPassword = getenv('SAASU_RESTRICTED_PASSWORD') ?: '';

if ($restrictedUsername === '' || $restrictedPassword === '') {
    $io->error('SAASU_RESTRICTED_USERNAME and SAASU_RESTRICTED_PASSWORD are not both set.');
    $io->line('If you are an agent: this is the guard working. Do not go looking for them - ask.');

    exit(1);
}

$fileId = harness_require_disposable_file($io);

$live = harness_mode($io, 'SAASU_WRITE', 'SAASU_AGENT_MAY_WRITE', sprintf(
    'file %s will get accounts, an item, a contact, a USD sale and a payment, then be swept',
    $fileId
));

$main = harness_client($io, 100);

if (!$live) {
    harness_spent($io, $main);

    exit(0);
}

$restricted = $main->withCredential(OAuth::password($restrictedUsername, $restrictedPassword));

$stamp = date('Ymd-His');
$today = new DateTimeImmutable('now', new DateTimeZone('Australia/Sydney'));
$failures = 0;
$leaks = [];
$verdicts = [];

// One call as the restricted user. A refusal is the answer being measured, not a failure of the run.
$ask = static function (string $label, callable $call) use ($io, &$verdicts): mixed {
    try {
        $result = $call();
        $verdicts[$label] = 'allowed';
        $io->values([$label => 'allowed']);

        return $result ?? true;
    } catch (ApiException $e) {
        $body = substr(str_replace(["\r", "\n"], ' ', trim($e->body)), 0, 200);
        $verdicts[$label] = sprintf('REFUSED %s, HTTP %d', (new ReflectionClass($e))->getShortName(), $e->statusCode);
        $io->values([$label => $verdicts[$label] . ' - body: ' . ($body === '' ? '(empty)' : $body)]);

        return null;
    }
};

$sale = static fn (int $contact, int $item, string $po): InvoiceRequest => InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Item, $today)
    ->withDueDate($today)
    ->withBillingContactId($contact)
    ->withAutoNumber()
    ->withTaxInclusive(true)
    ->withSummary("ZZ Sync permissions sale {$stamp}")
    ->withPurchaseOrderNumber($po)
    ->inCurrency('USD')
    ->addLineItem(InvoiceLineItemRequest::item($item, 1, 110.00, 'G1', 'ZZ Sync permissions line'));

try {
    $io->line();
    $io->info('setup, as the main login');

    $base = $main->files()->identity()->currencyCode ?? 'AUD';
    $g1 = null;

    foreach ($main->taxCodes()->all() as $code) {
        if ($code->code === 'G1') {
            $g1 = (int) $code->id;
        }
    }

    $fees = (int) $main->accounts()->create(AccountRequest::of("ZZ Sync Merchant Fees {$stamp}", AccountType::Expense, $base))->id;
    $income = (int) $main->accounts()->create(AccountRequest::of("ZZ Sync Income {$stamp}", AccountType::Income, $base))->id;

    $bank = (int) $main->accounts()->create(
        AccountRequest::of("ZZ Sync PayPal USD {$stamp}", AccountType::Asset, 'USD')
            ->withBankAccount(true)->withBankAccountName('ZZ PayPal USD')->withBsb('062000')->withAccountNumber('55555555')
            ->withMerchantFeeAccountId($fees)
    )->id;

    $email = "zz-sync-{$stamp}@example.invalid";
    $contact = (int) $main->contacts()->create(ContactRequest::person('ZZ Sync Subscriber', $stamp)->asCustomer()->withEmailAddress($email))->id;

    $item = (int) $main->items()->create(
        ItemRequest::inventory("ZZ-SYNC-{$stamp}", 'ZZ Sync item')->withInventoried(false)->sold($income, 110.00, $g1)
    )->id;

    $io->values([
        'accounts' => "fees #{$fees}, income #{$income}, USD bank #{$bank}",
        'contact, item' => "#{$contact}, #{$item}",
    ]);

    $io->line();
    $io->info('the restricted user\'s token');

    $authentication = $restricted->authentication();
    assert($authentication instanceof OAuth);

    try {
        $io->values(['scope' => $authentication->currentGrant($restricted->connection())->scope]);
    } catch (ApiException $e) {
        $failures++;
        $io->error(sprintf('no token: %s, HTTP %d - stopping', $e::class, $e->statusCode));

        throw $e;
    }

    $window = DateRange::between($today->modify('-3 days')->format('Y-m-d'), $today->modify('+3 days')->format('Y-m-d'));

    $io->line();
    $io->info('reads, as the restricted user');

    $ask('FileIdentity', static fn () => $restricted->files()->identity());
    $ask('Account/{USD bank}', static fn () => $restricted->accounts()->get($bank));
    $ask('Account/{item income}', static fn () => $restricted->accounts()->get($income));
    $ask('Item/{id}', static fn () => $restricted->items()->get($item));
    $ask('Contacts?Email=', static fn () => $restricted->contacts()->list(ContactFilter::all()->withEmail($email)));
    $ask('Contact/{id}', static fn () => $restricted->contacts()->get($contact));
    $ask('Invoices?PurchaseOrderNumber=', static fn () => $restricted->invoices()->list(InvoiceFilter::all()->purchaseOrderNumber('ZZPPNOSUCH')));
    $ask('Payments intoAccount, paidBetween', static fn () => $restricted->payments()->list(PaymentFilter::all()->intoAccount($bank)->paidBetween($window)));

    $io->line();
    $io->info('writes, as the restricted user');

    $po = 'ZZPP' . strtoupper(bin2hex(random_bytes(6)));
    $saleId = $ask('Invoice insert (item layout, USD)', static fn () => (int) $restricted->invoices()->create($sale($contact, $item, $po))->id);

    if (!is_int($saleId)) {
        $io->line('  the main login raises the sale instead, so the payment is still asked');
        $saleId = (int) $main->invoices()->create($sale($contact, $item, $po))->id;
    }

    $ask('Payment insert (USD, fee, reference)', static fn () => $restricted->payments()->create(
        PaymentRequest::received($today, $bank, [PaymentItemRequest::of($saleId, 110.00)])
            ->inCurrency('USD')
            ->withFeeAmount(3.30)
            ->withReference($po)
            ->withSummary('ZZ Sync permissions payment')
    ));

    // What the main login sees afterwards: whether an allowed write really booked.
    $invoice = $main->invoices()->get($saleId);
    $io->line();
    $io->values(['sale, as the main login' => sprintf('owed %s, status %s', var_export($invoice->amountOwed, true), var_export($invoice->paymentStatus, true))]);

    $io->line();
    $io->info('summary');
    $io->values($verdicts);
} catch (ExceptionInterface|RuntimeException $e) {
    $failures++;
    $io->error($e::class . ': ' . $e->getMessage());

    if ($e instanceof ApiException && $e->body !== '') {
        $io->line('  raw body: ' . substr(str_replace(["\r", "\n"], ' ', $e->body), 0, 400));
    }
} finally {
    $io->line();
    $io->info('cleanup: sweeping every ZZ record, as the main login');

    $leaks = harness_sweep($main, $io);

    harness_spent($io, $main);
}

if ($leaks !== [] || $failures > 0) {
    exit(1);
}

<?php

/**
 * Exercise: log in as a restricted user and see whether Saasu holds an OAuth token to that user's
 * permissions. Reads by default; WRITES to the disposable file when opted in. About 90 requests.
 *
 * The idea being tested: an integration that only needs contacts logs in as a user who may only
 * touch contacts, so a leaked credential cannot raise an invoice. That is worth recommending only
 * if Saasu enforces it on the API as well as in its own screens.
 *
 * THE QUESTIONS:
 *
 *   1. CAN A RESTRICTED USER GET A TOKEN AT ALL, and what scope does its grant carry?
 *   2. WHAT DOES A READ OUTSIDE ITS PERMISSIONS ANSWER? NotPermittedException assumes 403; nothing
 *      has measured it. The dangerous answer is a 200 with an empty list, which looks like a file
 *      with no invoices - so every list is also read by the main login, and the counts compared.
 *      Every read the client offers is asked, and a refusal the client would not raise as a
 *      NotPermittedException is marked UNRECOGNISED - the case where a caller is told its request
 *      was wrong when the user's permissions were.
 *   3. DOES A WRITE OUTSIDE ITS PERMISSIONS FAIL? A throwaway account insert. If it succeeds, the
 *      restriction is not enforced on the API, and the main login deletes the account.
 *   4. DOES A WRITE INSIDE THEM WORK? A throwaway contact, created and deleted by the restricted
 *      user - "contacts only" might mean read-only.
 *   5. DO THE SALES SUMMARY AND SEARCH LEAK A SALE to a user who may not list invoices? The main
 *      login raises one, the restricted user looks for it, and the sweep removes it. The user also
 *      tries a sale insert and a payment against that sale: what a refused insert looks like.
 *
 * Needs SAASU_FILE_ID, SAASU_USERNAME and SAASU_PASSWORD for the main login, and
 * SAASU_RESTRICTED_USERNAME and SAASU_RESTRICTED_PASSWORD for a user in the same file. The writes
 * need SAASU_WRITE=1 (and SAASU_AGENT_MAY_WRITE=1 under an agent), and refuse any file but
 * SAASU_DISPOSABLE_FILE_ID. Both throwaway records are named ZZ, so the sweep finds a leak.
 *
 * @var Hampel\Rig\Io $io
 */

use Hampel\Saasu\Api\Authentication\OAuth;
use Hampel\Saasu\Api\Enum\AccountType;
use Hampel\Saasu\Api\Enum\InvoiceLayout;
use Hampel\Saasu\Api\Enum\InvoiceType;
use Hampel\Saasu\Api\Exception\ApiException;
use Hampel\Saasu\Api\Exception\ExceptionInterface;
use Hampel\Saasu\Api\Filter\DateRange;
use Hampel\Saasu\Api\Request\AccountRequest;
use Hampel\Saasu\Api\Request\ContactRequest;
use Hampel\Saasu\Api\Request\InvoiceLineItemRequest;
use Hampel\Saasu\Api\Request\InvoiceRequest;
use Hampel\Saasu\Api\Request\PaymentItemRequest;
use Hampel\Saasu\Api\Request\PaymentRequest;

require __DIR__ . '/lib/harness.php';
require __DIR__ . '/lib/sweep.php';

$io->title('saasu · permissions');

$restrictedUsername = getenv('SAASU_RESTRICTED_USERNAME') ?: '';
$restrictedPassword = getenv('SAASU_RESTRICTED_PASSWORD') ?: '';

if ($restrictedUsername === '' || $restrictedPassword === '') {
    $io->error('SAASU_RESTRICTED_USERNAME and SAASU_RESTRICTED_PASSWORD are not both set.');
    $io->line('If you are an agent: this is the guard working. Do not go looking for them - ask.');

    exit(1);
}

$live = harness_mode($io, 'SAASU_WRITE', 'SAASU_AGENT_MAY_WRITE', 'the restricted user will try to create an account, and create and delete a contact; the main login raises a sale, which is swept');

if ($live) {
    harness_require_disposable_file($io);
}

$main = harness_client($io, 120);
$restricted = $main->withCredential(OAuth::password($restrictedUsername, $restrictedPassword));

/**
 * How many records a list body holds, or a description of why it is not a list.
 */
$count = static function (string $body): string {
    try {
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return 'not JSON';
    }

    foreach (is_array($decoded) ? $decoded : [] as $key => $value) {
        if ($key !== '_links' && is_array($value) && array_is_list($value)) {
            return (string) count($value);
        }
    }

    return 'no list in the body';
};

$describe = static fn (ApiException $e): string => sprintf('%s, HTTP %d: %s', (new ReflectionClass($e))->getShortName(), $e->statusCode, $e->getMessage());

$failure = null;
$leaks = [];

try {
    $io->info('1. the restricted user\'s token');

    $authentication = $restricted->authentication();
    assert($authentication instanceof OAuth);

    try {
        $grant = $authentication->currentGrant($restricted->connection());

        $io->success('a restricted user can get a token');
        $io->values([
            'scope' => $grant->scope,
            'file ids in the scope' => implode(', ', $grant->fileIds()) ?: '(none)',
        ]);
    } catch (ApiException $e) {
        $io->error('no token: ' . $describe($e));
        $io->warn('Nothing else can be asked - stopping.');
        harness_spent($io, $main);

        exit(1);
    }

    $io->line();
    $io->info('2. reads, restricted user against the main login - a restricted 200 with fewer records is the silent failure');

    $year = DateRange::between('2026-07-01', '2027-06-30');
    $paged = ['PageSize' => 100];

    // Every read the client offers that needs no record of its own, by label. Where Saasu requires
    // a parameter, it is given, so a refusal is about the user and not the request.
    $reads = [
        'FileIdentity' => ['FileIdentity', []],
        'User' => ['User', []],
        'Contacts' => ['Contacts', $paged],
        'Companies' => ['Companies', $paged],
        'Activities' => ['Activities', $paged],
        'Accounts' => ['Accounts', $paged],
        'Accounts/BankAccountBalances' => ['Accounts/BankAccountBalances', []],
        'TaxCodes' => ['TaxCodes', $paged],
        'Items' => ['Items', $paged],
        'ItemAdjustments' => ['ItemAdjustments', $paged],
        'ItemTransfers' => ['ItemTransfers', $paged],
        'Brands' => ['Brands', []],
        'Invoices' => ['Invoices', $paged],
        'Invoices, sales' => ['Invoices', $paged + ['TransactionType' => 'S']],
        'Invoices, purchases' => ['Invoices', $paged + ['TransactionType' => 'P']],
        'Invoices/SalesStatsSummary' => ['Invoices/SalesStatsSummary', $year->toQuery('InvoiceFromDate', 'InvoiceToDate')],
        'Payments' => ['Payments', $paged],
        'Payments, sale payments' => ['Payments', $paged + ['TransactionType' => 'SP']],
        'Payments, purchase payments' => ['Payments', $paged + ['TransactionType' => 'PP']],
        'Journals' => ['Journals', $paged],
        'DeletedEntities' => ['DeletedEntities', $paged],
        'P&L summary' => ['Reports/ProfitAndLoss/Summary', $year->toQuery('FromDate', 'ToDate')],
        'P&L by account type' => ['Reports/ProfitAndLoss/SummaryByAccountType', $year->toQuery('FromDate', 'ToDate')],
        'Search, all' => ['Search', ['Keywords' => 'zz', 'Scope' => 'All']],
        'Search, transactions' => ['Search', ['Keywords' => 'zz', 'Scope' => 'Transactions']],
        'LookupData/Countries' => ['LookupData/Countries', []],
        'Payroll/Employees' => ['Payroll/Employees', []],
        'Payroll/Entitlements' => ['Payroll/Entitlements', []],
        'Payroll/PayrollEntries' => ['Payroll/PayrollEntries', $paged],
    ];

    $verdicts = [];

    foreach ($reads as $label => [$path, $query]) {
        $asRestricted = harness_raw($restricted, 'GET', $path, $query);
        $asMain = harness_raw($main, 'GET', $path, $query);
        $restrictedBody = (string) $asRestricted->getBody();
        $mainBody = (string) $asMain->getBody();
        $restrictedStatus = $asRestricted->getStatusCode();
        $mainStatus = $asMain->getStatusCode();

        if ($restrictedStatus === 200) {
            $verdict = $mainStatus === 200 && $count($restrictedBody) !== $count($mainBody)
                ? sprintf('allowed, but FEWER RECORDS (%s against %s)', $count($restrictedBody), $count($mainBody))
                : 'allowed';
        } else {
            // What the client would raise. A refusal it does not raise as NotPermittedException is
            // the finding this exists for.
            $class = (new ReflectionClass(ApiException::fromResponse('GET', $path, $asRestricted, $restrictedBody)))->getShortName();
            $verdict = sprintf('refused, HTTP %d, %s', $restrictedStatus, $class);

            if ($mainStatus !== 200) {
                $verdict .= sprintf(' - main login also HTTP %d, so not a permission answer', $mainStatus);
            } elseif ($class !== 'NotPermittedException') {
                $verdict .= ' - UNRECOGNISED REFUSAL';
            }
        }

        $verdicts[$label] = $verdict;

        $io->line("  {$label}");
        $io->values([
            '    restricted' => harness_describe($asRestricted, 120) . ($restrictedStatus === 200 ? ' [records: ' . $count($restrictedBody) . ']' : ''),
            '    main' => sprintf('HTTP %d [records: %s]', $mainStatus, $count($mainBody)),
        ]);
    }

    $files = harness_raw($restricted, 'GET', 'FileIdentities', inFile: false);
    $io->line('  FileIdentities, without a file id');
    $io->values(['    restricted' => harness_describe($files, 200)]);

    $io->line();
    $io->info('reads, summary');
    $io->values($verdicts);

    if (!$live) {
        $io->line();
        $io->warn('NOT PROVEN by this run: whether a write outside the user\'s permissions is refused,');
        $io->warn('and whether "contacts only" includes writing a contact.');
    } else {
        $io->line();
        $io->info('3. a write outside the permissions: an account insert');

        $accountName = harness_probe_name();

        try {
            $saved = $restricted->accounts()->create(AccountRequest::of($accountName, AccountType::Income, 'AUD'));

            $io->error(sprintf('NOT ENFORCED - the restricted user created account #%d', $saved->id));

            try {
                $main->accounts()->delete((int) $saved->id);
                $io->line('  deleted it with the main login');
            } catch (ExceptionInterface $e) {
                $leaks[] = "account #{$saved->id}";
                $io->error('  ✗ CLEANUP FAILED - run the sweep exercise: ' . $e->getMessage());
            }
        } catch (ApiException $e) {
            $io->success('refused: ' . $describe($e));
            $io->values(['    body' => substr(trim($e->body), 0, 300) ?: '(empty)']);
        }

        $io->line();
        $io->info('4. a write inside the permissions: a contact, created and deleted');

        try {
            $saved = $restricted->contacts()->create(ContactRequest::person(harness_probe_name(), 'Permissions'));
            $io->success(sprintf('created contact #%d', $saved->id));

            try {
                $restricted->contacts()->delete((int) $saved->id);
                $io->success('and deleted it, as the restricted user');
            } catch (ApiException $e) {
                $io->warn('the restricted user could not delete it: ' . $describe($e));

                try {
                    $main->contacts()->delete((int) $saved->id);
                    $io->line('  deleted it with the main login');
                } catch (ExceptionInterface $cleanup) {
                    $leaks[] = "contact #{$saved->id}";
                    $io->error('  ✗ CLEANUP FAILED - run the sweep exercise: ' . $cleanup->getMessage());
                }
            }
        } catch (ApiException $e) {
            $io->warn('refused: ' . $describe($e));
        }

        $io->line();
        $io->info('5. do the sales summary and search show a sale to a user who may not list invoices?');

        // Both answered 200 to a contacts-only user in an empty file, where a zero proves nothing.
        // So the main login raises one sale with an amount nothing else in the file has, and the
        // restricted user looks for it.
        try {
            $token = 'ZZLEAK' . date('His');
            $income = (int) $main->accounts()->create(AccountRequest::of("ZZ Leak Income {$token}", AccountType::Income, 'AUD'))->id;
            $buyer = (int) $main->contacts()->create(ContactRequest::person('ZZ Leak', $token)->asCustomer())->id;
            $today = new DateTimeImmutable('now', new DateTimeZone('Australia/Sydney'));

            $sale = (int) $main->invoices()->create(
                InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, $today)
                    ->withDueDate($today)
                    ->withBillingContactId($buyer)
                    ->withAutoNumber()
                    ->withSummary("ZZ Leak sale {$token}")
                    ->addLineItem(InvoiceLineItemRequest::service("ZZ Leak line {$token}", $income, 4321.00))
            )->id;

            // A sale and a payment outside the permissions: what a refused insert looks like. Never
            // measured before; only an account insert had been. The payment goes against the main
            // login's sale, so it is refused for the user and not for want of an invoice.
            $bank = (int) $main->accounts()->create(
                AccountRequest::of("ZZ Leak Bank {$token}", AccountType::Asset, 'AUD')
                    ->withBankAccount(true)->withBankAccountName('ZZ Leak Bank')->withBsb('062000')->withAccountNumber('55555555')
            )->id;

            $refusal = static function (string $label, callable $write) use ($io, $describe): void {
                try {
                    $saved = $write();
                    $io->error(sprintf('%s: NOT ENFORCED - created #%s (the sweep removes it)', $label, $saved->id));
                } catch (ApiException $e) {
                    $io->values([$label => $describe($e)]);
                    $io->values(['    body' => substr(trim($e->body), 0, 300) ?: '(empty)']);
                }
            };

            $refusal('sale insert, restricted', static fn () => $restricted->invoices()->create(
                InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, $today)
                    ->withDueDate($today)
                    ->withBillingContactId($buyer)
                    ->withAutoNumber()
                    ->withSummary("ZZ Leak refused sale {$token}")
                    ->addLineItem(InvoiceLineItemRequest::service("ZZ Leak refused line {$token}", $income, 10.00))
            ));

            $refusal('payment insert, restricted', static fn () => $restricted->payments()->create(
                PaymentRequest::received($today, $bank, [PaymentItemRequest::of($sale, 10.00)])
                    ->withSummary("ZZ Leak refused payment {$token}")
            ));

            $year = DateRange::between('2026-07-01', '2027-06-30')->toQuery('InvoiceFromDate', 'InvoiceToDate');
            $search = ['Keywords' => $token, 'Scope' => 'Transactions'];

            $io->values([
                'summary, restricted' => harness_describe(harness_raw($restricted, 'GET', 'Invoices/SalesStatsSummary', $year), 120),
                'summary, main' => harness_describe(harness_raw($main, 'GET', 'Invoices/SalesStatsSummary', $year), 120),
                'search, restricted' => harness_describe(harness_raw($restricted, 'GET', 'Search', $search), 160),
                'search, main' => harness_describe(harness_raw($main, 'GET', 'Search', $search), 160),
            ]);
        } catch (ApiException $e) {
            $io->error('setup or read failed: ' . $describe($e));
            $failure = $e;
        } finally {
            $leaks = [...$leaks, ...harness_sweep($main, $io)];
        }
    }
} catch (ExceptionInterface $e) {
    $failure = $e;
    $io->error($e::class);
    $io->error($e->getMessage());
}

harness_spent($io, $main);

if ($failure !== null || $leaks !== []) {
    exit(1);
}

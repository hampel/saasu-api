<?php

/**
 * Exercise: probe how Saasu's lists page, filter and fail. Read-only. Spends about twenty requests.
 *
 * Each probe is a claim in this package that came from documentation prose, a generated sample
 * or a guess, and which a mocked suite answers the way the mock was written:
 *
 *   1. IS A FILTER HONOURED, AND IS A MISSPELT ONE IGNORED? Filter\Filter says an unrecognised
 *      parameter widens a query rather than being refused. Asked for an email that cannot exist,
 *      an honoured filter gives 0; an ignored one gives the unfiltered count, looking like success.
 *   2. HOW DOES A LIST END? Page::hasMore() stops at the first short page, because every
 *      documented sample carries a `next` link and nothing says it is ever absent. Is it?
 *      And what does a page past the end answer - an empty list, or an error?
 *   3. DO PAYMENTS REALLY DEFAULT TO THE LAST MONTH, and deleted entities to the last day?
 *      Both defaults are documented; both are the kind of thing that changes quietly.
 *   4. IS THE EMPLOYEE LIST PAGED? Endpoint\Employees sends no page parameters. A count of
 *      exactly 25 - Saasu's default page size - would say it is truncated.
 *   5. WHAT DOES A MISSING RECORD ANSWER? Endpoint::apiFind() turns a 404 into null. Saasu's own
 *      tests show a missing transaction answering "The requested transaction is not found." with
 *      no status asserted - if that is a 400, find() raises where it should answer null.
 *   6. WHAT DO DATES LOOK LIKE ON THE WIRE? Cast::date() ignores any offset on a transaction
 *      date; Cast::datetime() converts an instant. Both are right only if the raw strings are
 *      the shapes they assume.
 *   7. DOES A LAST-MODIFIED FILTER TAKE A TIME? The filter classes send dates only. If Saasu
 *      accepts a timestamp, a sync can be exact rather than re-reading a whole day.
 *
 * Needs SAASU_FILE_ID and either SAASU_USERNAME with SAASU_PASSWORD, or SAASU_WS_ACCESS_KEY.
 *
 * @var Hampel\Rig\Io $io
 */

use Hampel\Saasu\Api\Exception\ExceptionInterface;
use Hampel\Saasu\Api\Support\Cast;
use Hampel\Saasu\Api\Support\Json;

require __DIR__ . '/lib/harness.php';

$io->title('saasu · lists');

$saasu = harness_client($io);

/**
 * @param  array<string, scalar|null>  $query
 * @return array{int, list<mixed>, array<mixed>}  status, the items under $key, and the whole body
 */
$list = static function (string $path, string $key, array $query) use ($saasu): array {
    $response = harness_raw($saasu, 'GET', $path, $query);
    [, $body] = Json::decode((string) $response->getBody());
    $body = Cast::array($body);

    return [$response->getStatusCode(), array_values(Cast::array($body[$key] ?? null)), $body];
};

$hasNext = static function (array $body): bool {
    foreach (Cast::array($body['_links'] ?? null) as $link) {
        if (strcasecmp(Cast::string(Cast::object($link)['rel'] ?? null) ?? '', 'next') === 0) {
            return true;
        }
    }

    return false;
};

try {
    $io->info('1. filters');

    $needle = 'zzz-no-such-contact-' . bin2hex(random_bytes(4)) . '@example.invalid';
    [, $unfiltered] = $list('Contacts', 'Contacts', ['Page' => 1, 'PageSize' => 20]);
    [, $filtered] = $list('Contacts', 'Contacts', ['Page' => 1, 'PageSize' => 20, 'Email' => $needle]);
    [, $misspelt] = $list('Contacts', 'Contacts', ['Page' => 1, 'PageSize' => 20, 'Emial' => $needle]);

    $io->values([
        'contacts, unfiltered' => count($unfiltered),
        'contacts, Email=<nonsense>' => count($filtered),
        'contacts, Emial=<nonsense>' => count($misspelt),
    ]);

    if ($unfiltered === []) {
        $io->warn('the file has no contacts, so the filter probe cannot tell honoured from ignored');
    } elseif ($filtered === []) {
        $io->success('the Email filter is honoured');
    } else {
        $io->error('IGNORED - Email=<nonsense> returned contacts. Contacts::firstWithEmail() cannot be trusted.');
    }

    if ($unfiltered !== [] && count($misspelt) === count($unfiltered)) {
        $io->success('a misspelt filter is silently ignored, as Filter\Filter warns');
    } elseif ($unfiltered !== []) {
        $io->warn('a misspelt filter changed the result - Saasu may be rejecting unknown parameters');
    }

    $io->line();
    $io->info('2. how a list ends');

    [$status, $codes, $body] = $list('TaxCodes', 'TaxCodes', ['Page' => 1, 'PageSize' => 100]);

    $io->values([
        'tax codes on page 1 of 100' => count($codes),
        'next link on that page' => $hasNext($body) ? 'PRESENT' : 'absent',
    ]);

    if (count($codes) < 100 && $hasNext($body)) {
        $io->warn('a short last page still carries a next link - hasMore() is right to ignore it');
    } elseif (count($codes) < 100) {
        $io->success('a short last page carries no next link - the link could be trusted after all');
    }

    [$status, $beyond, $body] = $list('TaxCodes', 'TaxCodes', ['Page' => 999, 'PageSize' => 100]);

    $io->values([
        'page 999' => sprintf('HTTP %d, %d items, next link %s', $status, count($beyond), $hasNext($body) ? 'PRESENT' : 'absent'),
    ]);

    $io->line();
    $io->info('3. the default windows');

    [, $paymentsDefault] = $list('Payments', 'PaymentTransactions', ['Page' => 1, 'PageSize' => 100]);
    [, $paymentsWide] = $list('Payments', 'PaymentTransactions', [
        'Page' => 1,
        'PageSize' => 100,
        'PaymentFromDate' => date('Y-m-d', strtotime('-10 years')),
        'PaymentToDate' => date('Y-m-d', strtotime('+1 year')),
    ]);

    $io->values([
        'payments, no date range' => count($paymentsDefault),
        'payments, ten years' => count($paymentsWide),
    ]);

    if (count($paymentsWide) > count($paymentsDefault)) {
        $io->success('the unranged list is narrower - the last-month default is real');
    } elseif ($paymentsWide !== []) {
        $io->warn('the same count either way - the default window may not be what PaymentFilter says');
    }

    [, $deletedDefault] = $list('DeletedEntities', 'Entities', ['Page' => 1, 'PageSize' => 100]);
    [, $deletedWide] = $list('DeletedEntities', 'Entities', [
        'Page' => 1,
        'PageSize' => 100,
        'UtcDeletedFromDate' => date('Y-m-d', strtotime('-1 year')),
        'UtcDeletedToDate' => date('Y-m-d', strtotime('+1 day')),
    ]);

    $io->values([
        'deleted, no date range' => count($deletedDefault),
        'deleted, one year' => count($deletedWide),
    ]);

    $io->line();
    $io->info('4. is the employee list paged?');

    $employees = $saasu->employees()->all();

    $io->values(['employees returned' => count($employees)]);

    if (count($employees) === 25) {
        $io->warn('EXACTLY 25 - Saasu\'s default page size. The list is probably truncated; Employees::all() needs paging.');
    }

    $io->line();
    $io->info('5. what a missing record answers');

    foreach (['Contact', 'Invoice', 'Journal', 'Item', 'ItemAdjustment', 'Payment'] as $resource) {
        $io->values([$resource => harness_describe(harness_raw($saasu, 'GET', $resource . '/2147483000'), 100)]);
    }

    $io->line('  a status other than 404 here means find() raises instead of answering null');

    $io->line();
    $io->info('6. dates on the wire, as sent');

    [, $invoices] = $list('Invoices', 'Invoices', ['Page' => 1, 'PageSize' => 1]);
    $invoice = Cast::object($invoices[0] ?? null);
    $payment = Cast::object($paymentsWide[0] ?? null);

    $io->values([
        'Invoice.TransactionDate' => var_export($invoice['TransactionDate'] ?? '(no invoices)', true),
        'Invoice.DueDate' => var_export($invoice['DueDate'] ?? null, true),
        'Invoice.CreatedDateUtc' => var_export($invoice['CreatedDateUtc'] ?? null, true),
        'Payment.TransactionDate' => var_export($payment['TransactionDate'] ?? '(no payments)', true),
        'Payment.ClearedDate' => var_export($payment['ClearedDate'] ?? null, true),
    ]);

    $io->line('  a transaction date carrying a non-midnight time or an offset is the case Cast::date() guesses at');

    $io->line();
    $io->info('7. does a last-modified filter take a time?');

    $response = harness_raw($saasu, 'GET', 'Contacts', [
        'Page' => 1,
        'PageSize' => 1,
        'LastModifiedFromDate' => date('Y-m-d\TH:i:s', strtotime('-30 days')),
        'LastModifiedToDate' => date('Y-m-d\TH:i:s'),
    ]);

    $io->line('  ' . harness_describe($response, 120));
} catch (ExceptionInterface $e) {
    $io->error($e::class);
    $io->error($e->getMessage());
    harness_spent($io, $saasu);

    exit(1);
}

harness_spent($io, $saasu);

<?php

/**
 * Exercise: log in as a restricted user and see whether Saasu holds an OAuth token to that user's
 * permissions. Reads by default; WRITES to the disposable file when opted in. About 35 requests.
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
 *   3. DOES A WRITE OUTSIDE ITS PERMISSIONS FAIL? A throwaway account insert. If it succeeds, the
 *      restriction is not enforced on the API, and the main login deletes the account.
 *   4. DOES A WRITE INSIDE THEM WORK? A throwaway contact, created and deleted by the restricted
 *      user - "contacts only" might mean read-only.
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
use Hampel\Saasu\Api\Exception\ApiException;
use Hampel\Saasu\Api\Exception\ExceptionInterface;
use Hampel\Saasu\Api\Request\AccountRequest;
use Hampel\Saasu\Api\Request\ContactRequest;

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

$live = harness_mode($io, 'SAASU_WRITE', 'SAASU_AGENT_MAY_WRITE', 'the restricted user will try to create an account, and create and delete a contact');

if ($live) {
    harness_require_disposable_file($io);
}

$main = harness_client($io, 80);
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

    $reads = [
        'FileIdentity' => [],
        'User' => [],
        'Contacts' => ['PageSize' => 100],
        'Companies' => ['PageSize' => 100],
        'Accounts' => ['PageSize' => 100],
        'TaxCodes' => ['PageSize' => 100],
        'Items' => ['PageSize' => 100],
        'Invoices' => ['PageSize' => 100],
        'Payments' => ['PageSize' => 100],
        'Journals' => ['PageSize' => 100],
        'Activities' => ['PageSize' => 100],
    ];

    foreach ($reads as $path => $query) {
        $asRestricted = harness_raw($restricted, 'GET', $path, $query);
        $asMain = harness_raw($main, 'GET', $path, $query);
        $restrictedBody = (string) $asRestricted->getBody();
        $mainBody = (string) $asMain->getBody();

        $io->line("  {$path}");
        $io->values([
            '    restricted' => harness_describe($asRestricted, 120) . ($asRestricted->getStatusCode() === 200 ? ' [records: ' . $count($restrictedBody) . ']' : ''),
            '    main' => sprintf('HTTP %d [records: %s]', $asMain->getStatusCode(), $count($mainBody)),
        ]);
    }

    $files = harness_raw($restricted, 'GET', 'FileIdentities', inFile: false);
    $io->line('  FileIdentities, without a file id');
    $io->values(['    restricted' => harness_describe($files, 200)]);

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

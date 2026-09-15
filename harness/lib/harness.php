<?php

/**
 * Not an exercise. Discovery is glob('harness/*.php'), top level only, so a file down here is
 * required by the exercises that need it rather than listed as one of them.
 *
 * This carries the third of the three layers that keep an accident out of this harness:
 *
 *   1. rig itself does not load the package's .env when CLAUDECODE is set.
 *   2. every exercise reads, except the *-write exercises, which do nothing unless opted in - and
 *      all but contact-write also refuse any file but SAASU_DISPOSABLE_FILE_ID.
 *   3. this: the opt-in itself is refused under an agent.
 *
 * WHY THIS MATTERS MORE HERE THAN IN MOST PACKAGES. A Saasu login is not scoped: the password
 * that reads a file can raise invoices and email them to customers. And every request - reads
 * included, token requests included - spends the file's daily quota, which when exhausted
 * blocks every integration on the file for 24 hours. So every client built here carries a
 * request budget, and prints what it spent.
 */

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\HttpFactory;
use Hampel\Rig\Io;
use Hampel\Saasu\Api\Authentication\OAuth;
use Hampel\Saasu\Api\Authentication\WsAccessKey;
use Hampel\Saasu\Api\Client;
use Hampel\Saasu\Api\Config;
use Psr\Http\Message\ResponseInterface;

/**
 * The most requests one exercise run may send. Generous for what the exercises do - the
 * largest needs about twenty - and small against any plan's daily quota.
 */
const HARNESS_REQUEST_BUDGET = 60;

/**
 * Whether a real effect must be refused: an agent is running, and has not been told that this
 * once it may. Both reads fail safe - see the rig-harness notes in CLAUDE.md.
 */
function harness_agent_refuses(string $variable): bool
{
    return getenv('CLAUDECODE') !== false && getenv($variable) !== '1';
}

/**
 * A client built from the environment, or an explanation and an exit.
 *
 * OAuth when SAASU_USERNAME is set, the web services access key otherwise. The token store is
 * the in-memory default, so each run spends one request on a token - deliberately, since a
 * token cached on disk here would be a twelve-month credential sitting in a working copy.
 */
function harness_client(Io $io, int $budget = HARNESS_REQUEST_BUDGET): Client
{
    $fileId = (int) (getenv('SAASU_FILE_ID') ?: 0);
    $username = getenv('SAASU_USERNAME') ?: '';
    $password = getenv('SAASU_PASSWORD') ?: '';
    $key = getenv('SAASU_WS_ACCESS_KEY') ?: '';

    if ($username === '' && $key === '') {
        $io->error('Neither SAASU_USERNAME nor SAASU_WS_ACCESS_KEY is set.');
        $io->line();
        $io->line('If you are a person: copy .env.example to .env beside the package and fill it in.');
        $io->line('If you are an agent: this is the guard working. Do not go looking for the');
        $io->line('credentials, and do not edit .env - ask.');

        exit(1);
    }

    if ($fileId < 1) {
        $io->error('SAASU_FILE_ID is not set. Nearly every Saasu request names the file it acts on.');

        exit(1);
    }

    $authentication = $username !== ''
        ? OAuth::password($username, $password)
        : new WsAccessKey($key);

    $factory = new HttpFactory();

    return new Client(
        new Config($fileId, requestBudget: $budget),
        $authentication,
        new Guzzle(['timeout' => 60]),
        $factory,
        $factory,
    );
}

/**
 * Print the mode ABOVE the work, and answer whether the real effect may happen.
 */
function harness_mode(Io $io, string $optIn, string $override, string $effect): bool
{
    if (getenv($optIn) !== '1') {
        $io->warn('mode: read-only - nothing will be changed');
        $io->line(sprintf('      what is skipped: %s', $effect));
        $io->line(sprintf('      set %s=1 to do it for real', $optIn));
        $io->line();

        return false;
    }

    if (harness_agent_refuses($override)) {
        $io->warn(sprintf('mode: read-only - %s is set, and is ignored in an agent session', $optIn));
        $io->line(sprintf('      what is skipped: %s', $effect));
        $io->line(sprintf('      %s=1 on the command line unlocks it for one run', $override));
        $io->line();

        return false;
    }

    $io->warn(sprintf('mode: LIVE against the real file - %s', $effect));
    $io->line();

    return true;
}

/**
 * A request sent through the client's own credential, throttle and budget, answered RAW - status
 * and body as they arrived, with nothing turned into an exception.
 *
 * The package maps statuses to exception types, and when the question is which status Saasu
 * sends, the mapping hides the answer.
 *
 * @param  array<string, scalar|null>  $query
 */
function harness_raw(Client $saasu, string $method, string $path, array $query = [], bool $inFile = true): ResponseInterface
{
    $connection = $inFile ? $saasu->connection() : $saasu->connection()->withoutFile();
    $request = $connection->authentication()->authenticate($connection->request($method, $path, $query), $connection);

    $connection->throttle()->wait();
    $connection->counter()->increment();

    return $connection->client()->sendRequest($request);
}

/**
 * One line describing a raw response, with the body cut short.
 */
function harness_describe(ResponseInterface $response, int $excerpt = 160): string
{
    $body = trim((string) $response->getBody());

    return sprintf(
        'HTTP %d, %s, %d bytes%s',
        $response->getStatusCode(),
        $response->getHeaderLine('Content-Type') ?: 'no content type',
        strlen($body),
        $body === '' ? '' : ': ' . str_replace(["\r", "\n"], ' ', substr($body, 0, $excerpt))
    );
}

function harness_spent(Io $io, Client $saasu): void
{
    $io->line();
    $io->info(sprintf('requests spent against the daily quota: %d', $saasu->requestsSent()));
}

/**
 * A name for a throwaway record, chosen so a failed cleanup is obvious in Saasu's own screens.
 */
function harness_probe_name(): string
{
    return sprintf('ZZ DELETE ME saasu-api harness %s', date('Ymd-His'));
}

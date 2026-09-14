<?php

/**
 * Exercise: authenticate, read the file and the login, and settle the token questions.
 * Read-only - nothing in the file changes. Spends about six requests.
 *
 * QUESTIONS THIS SETTLES THAT THE SUITE CANNOT:
 *
 *   - does a password grant work, and what is the real `expires_in`? Everything known about
 *     token lifetime is one documentation sample showing 10800.
 *   - does the scope list the same files as FileIdentities? The package reads file ids out of
 *     the scope and calls it the cheapest answer; that is worth checking once.
 *   - DOES A REFRESH ROTATE THE REFRESH TOKEN? If it does, two processes refreshing the same
 *     grant race and the loser falls back to its password - which is the argument for a store
 *     with a lock. If it does not, they do not race at all.
 *   - does `authorisation/ping` answer a bare `true`, and what does it answer with a web
 *     services access key?
 *   - does any response carry a header describing the rate limit or the remaining quota?
 *     Saasu documents none, and one would change how a throttle should be built.
 *
 * Needs SAASU_FILE_ID and either SAASU_USERNAME with SAASU_PASSWORD, or SAASU_WS_ACCESS_KEY.
 *
 * @var Hampel\Rig\Io $io
 */

use Hampel\Saasu\Api\Authentication\OAuth;
use Hampel\Saasu\Api\Exception\ExceptionInterface;

require __DIR__ . '/lib/harness.php';

$io->title('saasu · file');

$saasu = harness_client($io);
$authentication = $saasu->authentication();

try {
    if ($authentication instanceof OAuth) {
        $grant = $authentication->currentGrant($saasu->connection());
        $seconds = $grant->expiresAt->getTimestamp() - time();

        $io->success('the password grant worked');
        $io->values([
            'expires in' => sprintf('about %d seconds (%.1f hours)', $seconds, $seconds / 3600),
            'refresh token' => $grant->refreshToken === null ? 'NONE - the grant cannot be refreshed' : 'present',
            'scope' => $grant->scope,
            'file ids in the scope' => implode(', ', $grant->fileIds()) ?: '(none)',
        ]);

        if (!$grant->grantsFile((int) getenv('SAASU_FILE_ID'))) {
            $io->warn('SAASU_FILE_ID is not among the files the scope names');
        }
    } else {
        $io->info('authenticating with a web services access key - the token questions do not apply');
    }

    $io->line();
    $io->info('the file');

    $identity = $saasu->verify();

    $io->values([
        'name' => $identity->name,
        'legal name' => $identity->fullLegalName,
        'zone' => $identity->zone,
        'currency' => $identity->currencyCode,
        'tax registered' => $identity->isTaxRegistered ? 'yes' : 'no',
        'year end' => sprintf('day %s of month %s', $identity->financialYearEndDay ?? '?', $identity->financialYearEndMonth ?? '?'),
    ]);

    $io->line();
    $io->info('every file this credential reaches');

    foreach ($saasu->files()->all() as $file) {
        $io->values([(string) $file->id => sprintf('%s (%s, %s)', $file->name, $file->zone, $file->subscriptionName)]);
    }

    $io->line();
    $io->info('the login');

    $user = $saasu->user()->get();

    $io->values([
        'username' => $user->username,
        'two-factor' => $user->twoFactorEnabled ? 'ON - an unattended OAuth client cannot use this login' : 'off',
    ]);

    $io->line();
    $io->info('ping, raw, with the file id - without one it answers 401 "No FileId"');

    $ping = harness_raw($saasu, 'GET', 'authorisation/ping');
    $io->line('  ' . harness_describe($ping));

    $io->line();
    $io->info('response headers that might describe a limit');

    $interesting = array_filter(
        array_keys($ping->getHeaders()),
        static fn (string $name): bool => preg_match('/rate|limit|quota|remaining|retry|api-version|request-id/i', $name) === 1
    );

    if ($interesting === []) {
        $io->line('  none - no rate-limit or quota header on this response');
    } else {
        foreach ($interesting as $name) {
            $io->values([$name => $ping->getHeaderLine($name)]);
        }
    }

    if ($authentication instanceof OAuth && isset($grant) && $grant->refreshToken !== null) {
        $io->line();
        $io->info('does a refresh rotate the refresh token?');

        $refreshed = $saasu->authorisation()->refresh($grant->refreshToken);

        $io->values([
            'new access token' => $refreshed->accessToken === $grant->accessToken ? 'SAME as before' : 'different',
            'new refresh token' => match (true) {
                $refreshed->refreshToken === null => 'NOT SENT - the old one is kept',
                $refreshed->refreshToken === $grant->refreshToken => 'SAME - refreshes do not race',
                default => 'DIFFERENT - concurrent refreshes race; a shared store wants a lock',
            },
        ]);

        $io->line();
        $io->info('is the refresh token it replaced still accepted?');

        try {
            $saasu->authorisation()->refresh($grant->refreshToken);
            $io->line('  yes - an old refresh token keeps working after a refresh');
        } catch (ExceptionInterface $e) {
            $io->line('  no - ' . $e->getMessage());
        }
    }
} catch (ExceptionInterface $e) {
    $io->error($e::class);
    $io->error($e->getMessage());
    harness_spent($io, $saasu);

    exit(1);
}

harness_spent($io, $saasu);

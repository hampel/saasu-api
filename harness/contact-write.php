<?php

/**
 * Exercise: create a throwaway contact, probe the write semantics against it, and delete it.
 * THIS WRITES TO A REAL SAASU FILE - read the guards before running it. About ten requests.
 *
 * Read-only by default. It needs SAASU_WRITE=1 to do anything at all, and in an agent session
 * that is ignored unless SAASU_AGENT_MAY_WRITE=1 is passed on the command line as well.
 *
 * A contact is the probe because it is the least consequential record Saasu has: it posts
 * nothing to the ledger, sends nothing, and deletes cleanly. Nothing here raises an invoice.
 *
 * THE QUESTIONS - each one decides how this package tells people to write an update:
 *
 *   1. DOES A PUT STILL CLEAR WHAT IT DOES NOT SEND? It did on 2026-09-14 - a contact lost its
 *      email, tags and customer flag - which is why every update is built from() the record. If
 *      this ever reports the opposite, Request\Payload's warning can soften.
 *   2. WHAT DOES A STALE LastUpdatedId ANSWER? ConcurrencyException is recognised by a sentence
 *      taken from Saasu's own client tests. Is it still that sentence, and still a 400?
 *   3. WHAT DOES A MISSING LastUpdatedId ANSWER? The endpoints refuse one before sending; this
 *      sends one raw to see whether that refusal matches Saasu's.
 *   4. DOES A DELETED CONTACT ANSWER 404? Saasu's own tests say so, and find() depends on it.
 *
 * THE RECORD is named `ZZ DELETE ME saasu-api harness <timestamp>`, so a failed cleanup is
 * obvious in Saasu's contact list. It is deleted in a `finally`, and the run exits non-zero if
 * the delete did not happen.
 *
 * Needs SAASU_FILE_ID and either SAASU_USERNAME with SAASU_PASSWORD, or SAASU_WS_ACCESS_KEY.
 *
 * @var Hampel\Rig\Io $io
 */

use Hampel\Saasu\Api\Exception\ConcurrencyException;
use Hampel\Saasu\Api\Exception\ExceptionInterface;
use Hampel\Saasu\Api\Request\ContactRequest;

require __DIR__ . '/lib/harness.php';

$io->title('saasu · contact write');

$live = harness_mode($io, 'SAASU_WRITE', 'SAASU_AGENT_MAY_WRITE', 'a contact will be created, updated and deleted');

$saasu = harness_client($io);

if (!$live) {
    try {
        $page = $saasu->contacts()->list(null, 1, 1);

        $io->success(sprintf('the contact list is readable (%d on the first page of one)', count($page)));
        $io->line();
        $io->warn('NOT PROVEN by this run: whether a PUT clears omitted fields, what a stale or');
        $io->warn('missing LastUpdatedId answers, and whether a deleted contact answers 404.');
    } catch (ExceptionInterface $e) {
        $io->error($e::class);
        $io->error($e->getMessage());

        exit(1);
    }

    harness_spent($io, $saasu);

    exit(0);
}

$name = harness_probe_name();
$id = null;
$failure = null;
$leaked = false;

try {
    $io->info('create');

    $saved = $saasu->contacts()->create(
        ContactRequest::person('ZZ DELETE ME', $name)
            ->withEmailAddress('saasu-api-harness@example.invalid')
            ->withPositionTitle('kept unless a PUT clears it')
            ->withTags(['saasu-api-harness'])
    );

    $id = $saved->id;

    if ($id === null) {
        throw new RuntimeException('the insert answered without an id, so nothing else can run');
    }

    $io->values(['id' => $id, 'LastUpdatedId' => $saved->lastUpdatedId]);

    $io->line();
    $io->info('1. does a PUT clear what it does not send?');

    $contact = $saasu->contacts()->get($id);
    $partial = ContactRequest::make()
        ->withGivenName('ZZ DELETE ME')
        ->withFamilyName($name)
        ->with('LastUpdatedId', $contact->lastUpdatedId);

    $afterPartial = $saasu->contacts()->update($id, $partial);
    $reread = $saasu->contacts()->get($id);

    $io->values([
        'PositionTitle before' => $contact->positionTitle,
        'PositionTitle after' => $reread->positionTitle ?? '(null)',
        'Tags after' => implode(', ', $reread->tags) ?: '(none)',
    ]);

    if ($reread->positionTitle === null) {
        $io->warn('A PUT CLEARS OMITTED FIELDS. Building updates from() the record is essential, not just safe.');
    } else {
        $io->success('a PUT keeps what it was not sent');
    }

    $io->line();
    $io->info('2. a stale LastUpdatedId');

    try {
        $saasu->contacts()->update($id, ContactRequest::from($contact)->withPositionTitle('stale write'));
        $io->error('THE STALE WRITE WAS ACCEPTED - Saasu is not checking LastUpdatedId on contacts');
    } catch (ConcurrencyException $e) {
        $io->success('refused as a ConcurrencyException: ' . implode(' ', $e->messages()));
    } catch (ExceptionInterface $e) {
        $io->warn(sprintf('refused, but NOT recognised as a concurrency conflict - %s (HTTP %d)', $e::class, $e->getCode()));
        $io->line('  ' . $e->getMessage());
        $io->line('  ErrorDetail::CONCURRENCY_MARKER needs updating to match');
    }

    $io->line();
    $io->info('3. no LastUpdatedId at all, sent raw');

    $connection = $saasu->connection();
    $request = $connection->withJson(
        $connection->request('PUT', 'Contact/' . $id),
        ['GivenName' => 'ZZ DELETE ME', 'FamilyName' => $name]
    );
    $authenticated = $connection->authentication()->authenticate($request, $connection);
    $connection->throttle()->wait($connection::fileIdOf($authenticated));
    $connection->counter()->increment();
    $response = $connection->client()->sendRequest($authenticated);

    $io->line('  ' . harness_describe($response));

    unset($afterPartial);
} catch (ExceptionInterface|RuntimeException $e) {
    $failure = $e;

    $io->line();
    $io->error($e::class);
    $io->error($e->getMessage());
} finally {
    $io->line();

    if ($id === null) {
        $io->info('nothing was created, so there is nothing to clean up');
    } else {
        try {
            $saasu->contacts()->delete($id);
            $io->success(sprintf('cleaned up - contact %d deleted', $id));

            $io->line();
            $io->info('4. does the deleted contact answer 404?');
            $io->line('  ' . harness_describe(harness_raw($saasu, 'GET', 'Contact/' . $id)));
        } catch (ExceptionInterface $cleanup) {
            $leaked = true;

            $io->error('✗ CLEANUP FAILED - ' . $cleanup::class);
            $io->error($cleanup->getMessage());
            $io->error(sprintf('Delete contact %d ("ZZ DELETE ME %s") by hand.', $id, $name));
        }
    }

    harness_spent($io, $saasu);
}

// Outside the try, because PHP does not run a `finally` on exit().
if ($failure !== null || $leaked) {
    exit(1);
}

<?php

/**
 * Exercise: can a contact be found by a value it holds in a custom field? WRITES to the disposable
 * file - two throwaway contacts, deleted in a finally. About 25 requests.
 *
 * The use: an identifier from another system - a forum username or user id - kept on the contact,
 * so a payment made from a different email address can still be matched to the right contact.
 *
 * THE QUESTIONS:
 *
 *   1. IS A CustomField1 / CustomField2 FILTER HONOURED ON Contacts? None is documented, and a
 *      misspelt parameter is silently ignored - so two contacts with different values are
 *      created, and an ignored filter shows as both coming back rather than one.
 *   2. DOES SEARCH FIND A CONTACT BY A CUSTOM FIELD VALUE, a text one or a number?
 *   3. AND BY ITS ContactId (the contact reference)? The Contacts filter for it is documented, and
 *      is the fallback if custom fields cannot be searched - but only if it matches whole values:
 *      a prefix match would find user 12 when asked for user 1.
 *   4. DOES A LIST ROW CARRY THE CUSTOM FIELDS? If so, walking the list and matching locally works,
 *      at one request per hundred contacts.
 *
 * Needs SAASU_WRITE=1 (and SAASU_AGENT_MAY_WRITE=1 under an agent), and refuses any file but
 * SAASU_DISPOSABLE_FILE_ID. Both contacts are named ZZ, so the sweep finds a leak.
 *
 * @var Hampel\Rig\Io $io
 */

use Hampel\Saasu\Api\Enum\SearchScope;
use Hampel\Saasu\Api\Exception\ExceptionInterface;
use Hampel\Saasu\Api\Request\ContactRequest;

require __DIR__ . '/lib/harness.php';
require __DIR__ . '/lib/sweep.php';

$io->title('saasu · contact lookup');

harness_require_disposable_file($io);

if (!harness_mode($io, 'SAASU_WRITE', 'SAASU_AGENT_MAY_WRITE', 'two throwaway contacts will be created and deleted')) {
    $io->warn('NOT PROVEN by this run: anything - every question needs the two contacts.');

    exit(0);
}

$saasu = harness_client($io);

$stamp = date('His');
$probes = [];

foreach (['A', 'B'] as $label) {
    $probes[$label] = [
        'username' => sprintf('zzforum%s%s', strtolower($label), bin2hex(random_bytes(3))),
        'userid' => (string) random_int(700000000, 799999999),
        'reference' => sprintf('ZZXF-%s-%s', $stamp, $label),
    ];
}

$count = static function ($response, string $key): string {
    $decoded = json_decode((string) $response->getBody(), true);

    return is_array($decoded) && is_array($decoded[$key] ?? null) ? (string) count($decoded[$key]) : 'n/a';
};

$ids = [];
$failure = null;
$leaked = false;

try {
    foreach ($probes as $label => $probe) {
        $saved = $saasu->contacts()->create(
            ContactRequest::person(harness_probe_name(), "Lookup {$label}")
                ->withCustomField1($probe['username'])
                ->withCustomField2($probe['userid'])
                ->withContactReference($probe['reference'])
        );
        $ids[$label] = (int) $saved->id;
        $io->line(sprintf('  created contact %s #%d: CustomField1 %s, CustomField2 %s, ContactId %s', $label, $saved->id, $probe['username'], $probe['userid'], $probe['reference']));
    }

    $a = $probes['A'];

    $io->line();
    $io->info('1. filters on Contacts, looking for A - 1 means honoured, 2 means ignored');

    $io->values([
        'no filter' => $count(harness_raw($saasu, 'GET', 'Contacts'), 'Contacts'),
        'CustomField1=<A>' => $count(harness_raw($saasu, 'GET', 'Contacts', ['CustomField1' => $a['username']]), 'Contacts'),
        'CustomField2=<A>' => $count(harness_raw($saasu, 'GET', 'Contacts', ['CustomField2' => $a['userid']]), 'Contacts'),
        'ContactId=<A>' => $count(harness_raw($saasu, 'GET', 'Contacts', ['ContactId' => $a['reference']]), 'Contacts'),
        'ContactId=<A, lower case>' => $count(harness_raw($saasu, 'GET', 'Contacts', ['ContactId' => strtolower($a['reference'])]), 'Contacts'),
        'ContactId=<A less its last character>' => $count(harness_raw($saasu, 'GET', 'Contacts', ['ContactId' => substr($a['reference'], 0, -1)]), 'Contacts'),
        'ContactId=<A less its first character>' => $count(harness_raw($saasu, 'GET', 'Contacts', ['ContactId' => substr($a['reference'], 1)]), 'Contacts'),
    ]);

    $io->line();
    $io->info('does a list row carry the custom fields, for matching them locally?');

    $row = $saasu->contacts()->list(null, 1, 100)->items[0] ?? null;
    $io->values([
        'CustomField1 in a list row' => $row?->customField1 ?? '(absent)',
        'CustomField2 in a list row' => $row?->customField2 ?? '(absent)',
    ]);

    $io->line();
    $io->info('2 and 3. search, Contacts scope, for A - tried three times, five seconds apart, in case the index lags');

    $searched = [];

    foreach (['CustomField1' => $a['username'], 'CustomField2' => $a['userid'], 'ContactId' => $a['reference'], 'surname (control)' => 'Lookup A'] as $what => $keywords) {
        foreach ([1, 2, 3] as $attempt) {
            $found = $saasu->search()->query($keywords, SearchScope::Contacts);

            if ($found->totalContacts > 0 || $attempt === 3) {
                break;
            }

            sleep(5);
        }

        $matched = array_map(static fn ($match): string => (string) array_search($match->id, $ids, true), $found->contacts);
        $searched[$what] = sprintf('%d found (%s), attempt %d', $found->totalContacts, implode(', ', $matched) ?: 'none', $attempt);
    }

    $io->values($searched);
} catch (ExceptionInterface $e) {
    $failure = $e;
    $io->error($e::class);
    $io->error($e->getMessage());
} finally {
    $io->line();

    foreach ($ids as $label => $id) {
        try {
            $saasu->contacts()->delete($id);
            $io->line("  deleted contact {$label} #{$id}");
        } catch (ExceptionInterface $e) {
            $leaked = true;
            $io->error("  ✗ CLEANUP FAILED - contact {$label} #{$id}; run the sweep exercise");
        }
    }
}

harness_spent($io, $saasu);

if ($failure !== null || $leaked) {
    exit(1);
}

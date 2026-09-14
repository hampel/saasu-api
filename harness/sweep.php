<?php

/**
 * Exercise: delete every ZZ record the writing exercises left in the disposable file. WRITES.
 *
 * For a run that failed part-way, or a sweep that missed something. The writing exercises sweep
 * at the end of every run anyway, so this is only needed when one could not. Refuses any file but
 * SAASU_DISPOSABLE_FILE_ID, and needs SAASU_WRITE=1 - and SAASU_AGENT_MAY_WRITE=1 under an agent.
 *
 * @var Hampel\Rig\Io $io
 */

require __DIR__ . '/lib/harness.php';
require __DIR__ . '/lib/sweep.php';

$io->title('saasu · sweep');

$fileId = harness_require_disposable_file($io);

$live = harness_mode($io, 'SAASU_WRITE', 'SAASU_AGENT_MAY_WRITE', sprintf('every ZZ record in file %s will be deleted', $fileId));

$saasu = harness_client($io, 200);

$leaks = $live ? harness_sweep($saasu, $io) : [];

harness_spent($io, $saasu);

if ($leaks !== []) {
    exit(1);
}

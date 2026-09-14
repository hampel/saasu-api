<?php

/**
 * Not an exercise - the cleanup the writing exercises share, and the guard that makes it safe.
 */

use Hampel\Rig\Io;
use Hampel\Saasu\Api\Client;
use Hampel\Saasu\Api\Exception\ExceptionInterface;
use Hampel\Saasu\Api\Filter\DateRange;
use Hampel\Saasu\Api\Filter\InvoiceFilter;
use Hampel\Saasu\Api\Filter\ItemTransferFilter;
use Hampel\Saasu\Api\Filter\JournalFilter;

/**
 * The file id, or an explanation and an exit when it is not the disposable one.
 *
 * An exercise that fills a file with records and sweeps every `ZZ` record away afterwards may run
 * against one file only, named twice - SAASU_FILE_ID and SAASU_DISPOSABLE_FILE_ID - so a copied
 * .env pointed at a real file cannot be seeded or swept.
 */
function harness_require_disposable_file(Io $io): string
{
    $fileId = getenv('SAASU_FILE_ID') ?: '';
    $disposable = getenv('SAASU_DISPOSABLE_FILE_ID') ?: '';

    if ($disposable === '' || $fileId !== $disposable) {
        $io->error('Refusing: SAASU_FILE_ID does not equal SAASU_DISPOSABLE_FILE_ID.');
        $io->line('This exercise fills a file with throwaway records. Name the one file it may do that to.');

        exit(1);
    }

    return $fileId;
}

/**
 * Delete every record the writing exercises name `ZZ` - from this run or any earlier one - in the order
 * Saasu will accept: payments before the invoices they settle, transactions before the items and
 * accounts they reference, contacts before companies. Answers what would not delete.
 *
 * By name rather than by the ids this run recorded, because a run that fails part-way records
 * nothing useful, and the next run should be able to finish its cleanup. That is safe only
 * because the exercise refuses any file but the disposable one.
 *
 * @return list<string>
 */
function harness_sweep(Client $saasu, Io $io): array
{
    $leaks = [];
    $everything = DateRange::between('2000-01-01', '2099-12-31');
    $zz = static fn (?string $text): bool => $text !== null && str_starts_with($text, 'ZZ');

    $delete = static function (string $label, Closure $delete) use ($io, &$leaks): void {
        try {
            $delete();
            $io->line('  deleted ' . $label);
        } catch (ExceptionInterface $e) {
            $leaks[] = $label;
            $io->error(sprintf('  ✗ could not delete %s - %s', $label, $e->getMessage()));
        }
    };

    try {
        $invoices = array_values(array_filter($saasu->invoices()->all(InvoiceFilter::all()->datedBetween($everything)), static fn ($invoice): bool => $zz($invoice->summary)));
        $invoiceIds = array_map(static fn ($invoice): int => (int) $invoice->id, $invoices);

        foreach ($invoiceIds as $invoiceId) {
            foreach ($saasu->payments()->forInvoice($invoiceId) as $payment) {
                $delete("payment #{$payment->id} on invoice #{$invoiceId}", static fn () => $saasu->payments()->delete((int) $payment->id));
            }
        }

        foreach ($invoices as $invoice) {
            $delete("invoice #{$invoice->id} ({$invoice->summary})", static fn () => $saasu->invoices()->delete((int) $invoice->id));
        }

        foreach ($saasu->journals()->all(JournalFilter::all()->datedBetween($everything)) as $journal) {
            if ($zz($journal->summary)) {
                $delete("journal #{$journal->id}", static fn () => $saasu->journals()->delete((int) $journal->id));
            }
        }

        foreach ($saasu->activities()->all() as $activity) {
            if ($zz($activity->title)) {
                $delete("activity #{$activity->id}", static fn () => $saasu->activities()->delete((int) $activity->id));
            }
        }

        $items = array_values(array_filter($saasu->items()->all(), static fn ($item): bool => $zz($item->code)));
        $itemIds = array_map(static fn ($item): int => (int) $item->id, $items);

        // A combo build creates a transfer with no ZZ summary of its own, so a transfer is ours if
        // its summary is, or if any line names one of our items.
        foreach ($saasu->itemTransfers()->all(ItemTransferFilter::all()->datedBetween($everything)) as $summary) {
            $transfer = $saasu->itemTransfers()->get((int) $summary->id);
            $ours = $zz($transfer->summary) || array_intersect($itemIds, array_map(static fn ($line): int => (int) $line->inventoryItemId, $transfer->items)) !== [];

            if ($ours) {
                $delete("transfer #{$transfer->id}", static fn () => $saasu->itemTransfers()->delete((int) $transfer->id));
            }
        }

        // No range: measured 2026-09-14, ItemAdjustments answers a bodiless 400 to a ToDate of
        // 2099-12-31 that ItemTransfers and Invoices accept - and 200 to one ten years out.
        foreach ($saasu->itemAdjustments()->all() as $adjustment) {
            if ($zz($adjustment->summary)) {
                $delete("adjustment #{$adjustment->id}", static fn () => $saasu->itemAdjustments()->delete((int) $adjustment->id));
            }
        }

        usort($items, static fn ($a, $b): int => ($b->isCombo() <=> $a->isCombo()));

        foreach ($items as $item) {
            $delete("item #{$item->id} ({$item->code})", static fn () => $saasu->items()->delete((int) $item->id));
        }

        // Two passes over contacts and accounts, because each can be referenced by another of its
        // kind and refuses to delete until that one has gone. Measured 2026-09-14: a contact
        // manager is referenced by the contact it manages, and the "Exchange" account Saasu
        // creates beside a foreign-currency bank account is referenced by that bank account.
        $inTwoPasses = static function (array $records, Closure $label, Closure $remove) use ($io, &$leaks): void {
            foreach ([1, 2] as $pass) {
                $remaining = [];

                foreach ($records as $record) {
                    try {
                        $remove($record);
                        $io->line('  deleted ' . $label($record));
                    } catch (ExceptionInterface $e) {
                        if ($pass === 2) {
                            $leaks[] = $label($record);
                            $io->error(sprintf('  ✗ could not delete %s - %s', $label($record), $e->getMessage()));
                        }

                        $remaining[] = $record;
                    }
                }

                $records = $remaining;
            }
        };

        $inTwoPasses(
            array_values(array_filter($saasu->contacts()->all(), static fn ($contact): bool => $zz($contact->givenName))),
            static fn ($contact): string => "contact #{$contact->id} ({$contact->fullName()})",
            static fn ($contact) => $saasu->contacts()->delete((int) $contact->id),
        );

        foreach ($saasu->companies()->all() as $company) {
            if ($zz($company->name)) {
                $delete("company #{$company->id} ({$company->name})", static fn () => $saasu->companies()->delete((int) $company->id));
            }
        }

        $inTwoPasses(
            array_values(array_filter($saasu->accounts()->all(), static fn ($account): bool => $zz($account->name))),
            static fn ($account): string => "account #{$account->id} ({$account->name})",
            static fn ($account) => $saasu->accounts()->delete((int) $account->id),
        );
    } catch (ExceptionInterface $e) {
        $leaks[] = 'the sweep itself: ' . $e->getMessage();
        $io->error('  ✗ the sweep stopped - ' . $e->getMessage());
    }

    return $leaks;
}

<?php

/**
 * Exercise: generate the PDF of a sale, and read one invoice's attachments.
 * Read-only - generating a PDF sends nothing. Spends about five requests.
 *
 * QUESTIONS:
 *
 *   - does `generate-pdf` answer with PDF bytes, and with what content type and filename?
 *     Saasu documents it only as "raw PDF file content", so Result\Document reads both from
 *     headers it cannot know will be there.
 *   - what does `AttachmentData` look like on the wire - a base64 string, as a .NET server would
 *     write a byte array, or an array of numbers? Attachment::content() decodes either, and
 *     AttachmentRequest sends base64 on the strength of the first reading.
 *
 * Writes the PDF to the system temporary directory, never into the package, and prints where.
 *
 * Needs SAASU_FILE_ID and either SAASU_USERNAME with SAASU_PASSWORD, or SAASU_WS_ACCESS_KEY.
 *
 * @var Hampel\Rig\Io $io
 */

use Hampel\Saasu\Api\Exception\ExceptionInterface;
use Hampel\Saasu\Api\Filter\InvoiceFilter;

require __DIR__ . '/lib/harness.php';

$io->title('saasu · documents');

$saasu = harness_client($io);

try {
    $sales = $saasu->invoices()->list(InvoiceFilter::sales(), 1, 20);

    if ($sales->isEmpty()) {
        $io->warn('the file has no sales, so there is nothing to generate');
        harness_spent($io, $saasu);

        exit(0);
    }

    // The first sale Saasu lists - it documents no ordering, so not necessarily the newest.
    $invoice = $sales->first();

    $io->values([
        'invoice' => sprintf('#%d, %s, dated %s', $invoice->id, $invoice->invoiceNumber, $invoice->transactionDate?->format('Y-m-d') ?? '?'),
    ]);

    $document = $saasu->invoices()->pdf((int) $invoice->id);
    $path = sys_get_temp_dir() . sprintf('/saasu-invoice-%d.pdf', $invoice->id);
    file_put_contents($path, $document->content);

    $io->values([
        'is a PDF' => $document->isPdf() ? 'yes, by its signature' : 'NO - ' . substr($document->content, 0, 60),
        'content type' => $document->contentType ?? '(not sent)',
        'filename' => $document->filename ?? '(no Content-Disposition)',
        'size' => $document->size() . ' bytes',
        'written to' => $path,
    ]);

    $io->line();
    $io->info('attachments on the first of these sales that has any');

    foreach ($sales as $sale) {
        $attachments = $saasu->invoices()->attachments((int) $sale->id);

        if ($attachments === []) {
            continue;
        }

        $raw = harness_raw($saasu, 'GET', 'InvoiceAttachment/' . $attachments[0]->id);
        $body = json_decode((string) $raw->getBody(), true);
        $data = is_array($body) ? ($body['AttachmentData'] ?? null) : null;

        $io->values([
            'invoice' => $sale->id,
            'attachment' => sprintf('#%d %s (%s bytes by the list)', $attachments[0]->id, $attachments[0]->name, $attachments[0]->size ?? '?'),
            'AttachmentData is' => match (true) {
                is_string($data) => sprintf('a string of %d characters - base64, as assumed', strlen($data)),
                is_array($data) => sprintf('AN ARRAY of %d elements - AttachmentRequest must send an array', count($data)),
                default => 'null - Saasu did not send the contents at all',
            },
        ]);

        break;
    }

    if (!isset($data)) {
        $io->line('  none of the first twenty sales has an attachment');
    }
} catch (ExceptionInterface $e) {
    $io->error($e::class);
    $io->error($e->getMessage());
    harness_spent($io, $saasu);

    exit(1);
}

harness_spent($io, $saasu);

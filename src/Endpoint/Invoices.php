<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\AttachmentInfo;
use Hampel\Saasu\Api\Entity\Invoice;
use Hampel\Saasu\Api\Entity\SalesSummary;
use Hampel\Saasu\Api\Enum\PdfLayout;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Filter\DateRange;
use Hampel\Saasu\Api\Filter\InvoiceFilter;
use Hampel\Saasu\Api\Request\InvoiceRequest;
use Hampel\Saasu\Api\Result\Document;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Sales and purchases, at `Invoice` and `Invoices`.
 *
 * THREE OPERATIONS HERE SEND EMAIL TO REAL PEOPLE: email(), emailToContact(), and any write whose
 * request was given InvoiceRequest::withEmailToContact(). None of them can be recalled.
 */
final class Invoices extends Endpoint
{
    /**
     * One page of invoices - summaries, without line items.
     *
     * @return Page<Invoice>
     */
    public function list(?InvoiceFilter $filter = null, int $page = 1, ?int $pageSize = null): Page
    {
        return $this->apiPaginate('Invoices', 'Invoices', Invoice::fromArray(...), $filter ?? [], $page, $pageSize);
    }

    /**
     * Every matching invoice, a page at a time, as far as it is consumed. Each page is a request.
     *
     * @return \Generator<int, Invoice>
     */
    public function each(?InvoiceFilter $filter = null, ?int $pageSize = null): \Generator
    {
        return $this->apiEach('Invoices', 'Invoices', Invoice::fromArray(...), $filter ?? [], $pageSize);
    }

    /**
     * Every matching invoice, as a list - one request per page, so narrow the filter first.
     *
     * @return list<Invoice>
     */
    public function all(?InvoiceFilter $filter = null): array
    {
        return iterator_to_array($this->each($filter), false);
    }

    /**
     * One invoice by id, with its lines, notes and attachments.
     */
    public function get(int $id): Invoice
    {
        return $this->apiObject($this->path($id), Invoice::fromArray(...));
    }

    public function find(int $id): ?Invoice
    {
        return $this->apiFind($this->path($id), Invoice::fromArray(...));
    }

    /**
     * Insert an invoice. With InvoiceRequest::withAutoNumber(), Saved::generatedInvoiceNumber()
     * has the number Saasu gave it.
     */
    public function create(InvoiceRequest $request): Saved
    {
        return $this->apiSave('POST', 'Invoice', $request);
    }

    /**
     * Update an invoice, built from() the invoice as read.
     *
     * A QUICK PAYMENT IS REFUSED HERE, before sending: Saasu accepts one only on an insert. Record
     * a payment against an existing invoice with Payments::create().
     */
    public function update(int $id, InvoiceRequest $request): Saved
    {
        $this->assertUpdatable($request, 'invoice');

        if ($request->has('QuickPayment')) {
            throw new InvalidArgumentException(
                'Saasu accepts a quick payment only when an invoice is inserted. Record a payment against '
                    . 'an existing invoice with Payments::create().'
            );
        }

        return $this->apiSave('PUT', $this->path($id), $request, $id);
    }

    public function delete(int $id): void
    {
        $this->apiDelete($this->path($id));
    }

    /**
     * The invoice as a PDF.
     *
     * `$layout` is Saasu's `PrintAs`, honoured only in a file with the "new theme" on; without
     * one, and without a template, Saasu uses the default for a sale or purchase.
     */
    public function pdf(int $id, ?int $templateId = null, ?PdfLayout $layout = null): Document
    {
        return $this->apiDocument($this->path($id) . '/generate-pdf', [
            'TemplateId' => $templateId,
            'PrintAs' => $layout?->value,
        ]);
    }

    /**
     * EMAIL THE INVOICE to an address. Answers with Saasu's status message.
     */
    public function email(int $id, string $address): ?string
    {
        return $this->apiPost($this->path($id) . '/email', [
            'EmailTo' => Cast::required($address, 'Emailing an invoice needs an address to send it to.'),
        ])->statusMessage();
    }

    /**
     * EMAIL THE INVOICE to its billing contact. Answers with Saasu's status message.
     */
    public function emailToContact(int $id): ?string
    {
        return $this->apiPost($this->path($id) . '/email-contact')->statusMessage();
    }

    /**
     * Sales totals for a period.
     */
    public function salesSummary(?DateRange $period = null): SalesSummary
    {
        return SalesSummary::fromArray(
            $this->apiGet('Invoices/SalesStatsSummary', $period?->toQuery('InvoiceFromDate', 'InvoiceToDate') ?? [])->object()
        );
    }

    /**
     * What is attached to an invoice, without the contents.
     *
     * @return list<AttachmentInfo>
     */
    public function attachments(int $id): array
    {
        return Cast::objects(
            $this->apiGet('InvoiceAttachments/' . Cast::id($id, 'invoice'))->value('Attachments'),
            AttachmentInfo::fromArray(...)
        );
    }

    private function path(int $id): string
    {
        return 'Invoice/' . Cast::id($id, 'invoice');
    }
}

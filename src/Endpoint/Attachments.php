<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\Attachment;
use Hampel\Saasu\Api\Entity\AttachmentInfo;
use Hampel\Saasu\Api\Request\AttachmentRequest;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Attachments, at `InvoiceAttachment` and `InvoiceAttachments`.
 *
 * Named for invoices by Saasu; `ItemIdAttachedTo` is documented only as "the item/entity" the
 * attachment belongs to.
 */
final class Attachments extends Endpoint
{
    /**
     * One attachment with its contents - see Attachment::content().
     */
    public function get(int $id): Attachment
    {
        return $this->apiObject($this->path($id), Attachment::fromArray(...));
    }

    public function find(int $id): ?Attachment
    {
        return $this->apiFind($this->path($id), Attachment::fromArray(...));
    }

    /**
     * What is attached to an invoice, without the contents.
     *
     * @return list<AttachmentInfo>
     */
    public function forInvoice(int $invoiceId): array
    {
        return Cast::objects(
            $this->apiGet('InvoiceAttachments/' . Cast::id($invoiceId, 'invoice'))->value('Attachments'),
            AttachmentInfo::fromArray(...)
        );
    }

    /**
     * Upload an attachment. Answers with its id.
     */
    public function create(AttachmentRequest $request): ?int
    {
        $id = Cast::int($this->apiPost('InvoiceAttachment', $request)->value('Id'));

        return $id !== null && $id > 0 ? $id : null;
    }

    public function delete(int $id): void
    {
        $this->apiDelete($this->path($id));
    }

    private function path(int $id): string
    {
        return 'InvoiceAttachment/' . Cast::id($id, 'attachment');
    }
}

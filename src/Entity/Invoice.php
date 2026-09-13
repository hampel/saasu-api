<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Enum\InvoiceLayout;
use Hampel\Saasu\Api\Enum\InvoiceStatus;
use Hampel\Saasu\Api\Enum\InvoiceType;
use Hampel\Saasu\Api\Enum\TransactionType;
use Hampel\Saasu\Api\Support\Cast;

/**
 * A sale or a purchase - a quote, an order, a tax invoice, an adjustment note.
 *
 * ONE RESOURCE FOR BOTH SIDES OF THE LEDGER. `TransactionType` is `S` for a sale and `P` for a
 * purchase, and `InvoiceType` is what kind of document it is. `InvoiceStatus` - quote, order or
 * invoice - is derived from `InvoiceType`, deprecated, and read-only.
 *
 * THE ID IS `TransactionId`, and it is `$id` here. `InvoiceNumber` is the human number printed on
 * the document, and is not unique across sales and purchases.
 *
 * THE LIST CARRIES LESS THAN THE RECORD. `Invoices` answers summaries without line items,
 * notes, terms or attachments; those are empty here until the invoice is read by id.
 *
 * Amounts are floats - see Cast::float(). `AmountOwed` is what matters for "is it paid";
 * `PaymentStatus` says the same thing as a letter.
 */
final class Invoice extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?string $invoiceNumber = null,
        public readonly ?string $invoiceType = null,
        public readonly ?string $transactionType = null,
        public readonly ?string $layout = null,
        public readonly ?string $invoiceStatus = null,
        public readonly ?string $paymentStatus = null,
        public readonly ?\DateTimeImmutable $transactionDate = null,
        public readonly ?\DateTimeImmutable $dueDate = null,
        public readonly ?string $currency = null,
        public readonly ?float $fxRate = null,
        public readonly ?bool $autoPopulateFxRate = null,
        public readonly ?string $summary = null,
        public readonly ?string $purchaseOrderNumber = null,
        public readonly ?int $brandId = null,
        public readonly ?int $templateId = null,
        public readonly ?float $totalAmount = null,
        public readonly ?float $totalTaxAmount = null,
        public readonly ?bool $isTaxInc = null,
        public readonly ?float $amountPaid = null,
        public readonly ?float $amountOwed = null,
        public readonly ?int $paymentCount = null,
        public readonly ?bool $requiresFollowUp = null,
        public readonly ?bool $sentToContact = null,
        public readonly ?int $billingContactId = null,
        public readonly ?string $billingContactFirstName = null,
        public readonly ?string $billingContactLastName = null,
        public readonly ?string $billingContactOrganisationName = null,
        public readonly ?int $shippingContactId = null,
        public readonly ?string $shippingContactFirstName = null,
        public readonly ?string $shippingContactLastName = null,
        public readonly ?string $shippingContactOrganisationName = null,
        public readonly ?string $notesInternal = null,
        public readonly ?string $notesExternal = null,
        public readonly ?InvoiceTerms $terms = null,
        /** @var list<InvoiceLineItem> */
        public readonly array $lineItems = [],
        /** @var list<AttachmentInfo> */
        public readonly array $attachments = [],
        /** @var list<string> */
        public readonly array $tags = [],
        public readonly ?\DateTimeImmutable $createdDateUtc = null,
        public readonly ?\DateTimeImmutable $lastModifiedDateUtc = null,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): static
    {
        return new self(
            Cast::int($row['TransactionId'] ?? null),
            Cast::text($row['LastUpdatedId'] ?? null),
            Cast::text($row['InvoiceNumber'] ?? null),
            Cast::text($row['InvoiceType'] ?? null),
            Cast::text($row['TransactionType'] ?? null),
            Cast::text($row['Layout'] ?? null),
            Cast::text($row['InvoiceStatus'] ?? null),
            Cast::text($row['PaymentStatus'] ?? null),
            Cast::date($row['TransactionDate'] ?? null),
            Cast::date($row['DueDate'] ?? null),
            Cast::text($row['Currency'] ?? null),
            Cast::float($row['FxRate'] ?? null),
            Cast::bool($row['AutoPopulateFxRate'] ?? null),
            Cast::text($row['Summary'] ?? null),
            Cast::text($row['PurchaseOrderNumber'] ?? null),
            Cast::int($row['BrandId'] ?? null),
            Cast::int($row['TemplateId'] ?? null),
            Cast::float($row['TotalAmount'] ?? null),
            Cast::float($row['TotalTaxAmount'] ?? null),
            Cast::bool($row['IsTaxInc'] ?? null),
            Cast::float($row['AmountPaid'] ?? null),
            Cast::float($row['AmountOwed'] ?? null),
            Cast::int($row['PaymentCount'] ?? null),
            Cast::bool($row['RequiresFollowUp'] ?? null),
            Cast::bool($row['SentToContact'] ?? null),
            Cast::int($row['BillingContactId'] ?? null),
            Cast::text($row['BillingContactFirstName'] ?? null),
            Cast::text($row['BillingContactLastName'] ?? null),
            Cast::text($row['BillingContactOrganisationName'] ?? null),
            Cast::int($row['ShippingContactId'] ?? null),
            Cast::text($row['ShippingContactFirstName'] ?? null),
            Cast::text($row['ShippingContactLastName'] ?? null),
            Cast::text($row['ShippingContactOrganisationName'] ?? null),
            Cast::text($row['NotesInternal'] ?? null),
            Cast::text($row['NotesExternal'] ?? null),
            Cast::nested($row['Terms'] ?? null, InvoiceTerms::fromArray(...)),
            Cast::objects($row['LineItems'] ?? null, InvoiceLineItem::fromArray(...)),
            Cast::objects($row['Attachments'] ?? null, AttachmentInfo::fromArray(...)),
            Cast::tags($row['Tags'] ?? null),
            Cast::datetime($row['CreatedDateUtc'] ?? null),
            Cast::datetime($row['LastModifiedDateUtc'] ?? null),
            $row,
        );
    }

    public function transaction(): ?TransactionType
    {
        return Cast::enum(TransactionType::class, $this->transactionType);
    }

    public function isSale(): bool
    {
        return $this->transaction() === TransactionType::Sale;
    }

    public function isPurchase(): bool
    {
        return $this->transaction() === TransactionType::Purchase;
    }

    public function status(): ?InvoiceStatus
    {
        return Cast::enum(InvoiceStatus::class, $this->invoiceStatus);
    }

    public function type(): ?InvoiceType
    {
        return Cast::enum(InvoiceType::class, $this->invoiceType);
    }

    public function layout(): ?InvoiceLayout
    {
        return Cast::enum(InvoiceLayout::class, $this->layout);
    }

    /**
     * Whether nothing is owed - read from `AmountOwed` rather than `PaymentStatus`, because the
     * amount is what a payment changes and the letter is derived from it.
     */
    public function isPaid(): bool
    {
        return $this->amountOwed !== null && abs($this->amountOwed) < 0.005;
    }

    /**
     * Whether it is past its due date with money still owed, as of the given day.
     */
    public function isOverdue(\DateTimeInterface $today): bool
    {
        return !$this->isPaid() && $this->dueDate !== null && $this->dueDate->format('Y-m-d') < $today->format('Y-m-d');
    }

    public function billingContactName(): string
    {
        return trim(($this->billingContactFirstName ?? '') . ' ' . ($this->billingContactLastName ?? ''));
    }
}

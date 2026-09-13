<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A transaction found by search - fewer fields than the transaction itself.
 *
 * FROM A SEARCH INDEX, NOT THE LEDGER: an edit may not be visible here yet. `TotalAmountIncTax`
 * arrives as a string and `Tags` as one comma-separated string; both are converted.
 */
final class TransactionMatch extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $entityType = null,
        public readonly ?string $type = null,
        public readonly ?\DateTimeImmutable $transactionDate = null,
        public readonly ?\DateTimeImmutable $dueDate = null,
        public readonly ?string $summary = null,
        public readonly ?string $invoiceNumber = null,
        public readonly ?string $invoiceType = null,
        public readonly ?string $purchaseOrderNumber = null,
        public readonly ?string $reference = null,
        public readonly ?int $contactId = null,
        public readonly ?string $contactName = null,
        public readonly ?string $contactReference = null,
        public readonly ?string $contactEmail = null,
        public readonly ?string $company = null,
        public readonly ?string $tradingName = null,
        public readonly ?float $totalAmountIncTax = null,
        public readonly ?string $currency = null,
        public readonly ?bool $paid = null,
        public readonly ?string $notes = null,
        public readonly ?string $externalNotes = null,
        /** @var list<string> */
        public readonly array $tags = [],
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
            Cast::int($row['Id'] ?? null),
            Cast::text($row['EntityType'] ?? null),
            Cast::text($row['Type'] ?? null),
            Cast::date($row['TransactionDate'] ?? null),
            Cast::date($row['DueDate'] ?? null),
            Cast::text($row['Summary'] ?? null),
            Cast::text($row['InvoiceNumber'] ?? null),
            Cast::text($row['InvoiceType'] ?? null),
            Cast::text($row['PurchaseOrderNumber'] ?? null),
            Cast::text($row['Reference'] ?? null),
            Cast::int($row['ContactId'] ?? null),
            Cast::text($row['ContactName'] ?? null),
            Cast::text($row['CustomerContactId'] ?? null),
            Cast::text($row['ContactEmail'] ?? null),
            Cast::text($row['Company'] ?? null),
            Cast::text($row['TradingName'] ?? null),
            Cast::float($row['TotalAmountIncTax'] ?? null),
            Cast::text($row['Currency'] ?? null),
            Cast::bool($row['Paid'] ?? null),
            Cast::text($row['Notes'] ?? null),
            Cast::text($row['ExternalNotes'] ?? null),
            Cast::tags($row['Tags'] ?? null),
            $row,
        );
    }
}

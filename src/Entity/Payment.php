<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Enum\PaymentType;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Money received against sales, or paid against purchases - one payment can settle several
 * invoices, listed in `PaymentItems`.
 *
 * `TransactionType` is `SP` for a sale payment and `PP` for a purchase payment. The list answers
 * summaries without `Notes` or `PaymentItems`.
 */
final class Payment extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?\DateTimeImmutable $transactionDate = null,
        public readonly ?string $transactionType = null,
        public readonly ?int $paymentAccountId = null,
        public readonly ?float $totalAmount = null,
        public readonly ?float $feeAmount = null,
        public readonly ?string $summary = null,
        public readonly ?string $reference = null,
        public readonly ?string $notes = null,
        public readonly ?\DateTimeImmutable $clearedDate = null,
        public readonly ?string $currency = null,
        public readonly ?bool $autoPopulateFxRate = null,
        public readonly ?float $fxRate = null,
        public readonly ?bool $requiresFollowUp = null,
        /** @var list<PaymentItem> */
        public readonly array $paymentItems = [],
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
            Cast::date($row['TransactionDate'] ?? null),
            Cast::text($row['TransactionType'] ?? null),
            Cast::int($row['PaymentAccountId'] ?? null),
            Cast::float($row['TotalAmount'] ?? null),
            Cast::float($row['FeeAmount'] ?? null),
            Cast::text($row['Summary'] ?? null),
            Cast::text($row['Reference'] ?? null),
            Cast::text($row['Notes'] ?? null),
            Cast::date($row['ClearedDate'] ?? null),
            Cast::text($row['Currency'] ?? null),
            Cast::bool($row['AutoPopulateFxRate'] ?? null),
            Cast::float($row['FxRate'] ?? null),
            Cast::bool($row['RequiresFollowUp'] ?? null),
            Cast::objects($row['PaymentItems'] ?? null, PaymentItem::fromArray(...)),
            Cast::datetime($row['CreatedDateUtc'] ?? null),
            Cast::datetime($row['LastModifiedDateUtc'] ?? null),
            $row,
        );
    }

    public function paymentType(): ?PaymentType
    {
        return Cast::enum(PaymentType::class, $this->transactionType);
    }

    public function isCleared(): bool
    {
        return $this->clearedDate !== null;
    }
}

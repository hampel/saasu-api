<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A general journal - debits and credits posted straight to accounts.
 *
 * The id is `TransactionId`, as on an invoice. The list answers summaries without `Notes` or
 * `Items`.
 */
final class Journal extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?\DateTimeImmutable $transactionDate = null,
        public readonly ?string $summary = null,
        public readonly ?string $reference = null,
        public readonly ?string $notes = null,
        public readonly ?string $currency = null,
        public readonly ?float $fxRate = null,
        public readonly ?bool $autoPopulateFxRate = null,
        public readonly ?int $journalContactId = null,
        public readonly ?string $contactFirstName = null,
        public readonly ?string $contactLastName = null,
        public readonly ?string $contactOrganisationName = null,
        public readonly ?bool $requiresFollowUp = null,
        /** @var list<string> */
        public readonly array $tags = [],
        /** @var list<JournalItem> */
        public readonly array $items = [],
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
            Cast::text($row['Summary'] ?? null),
            Cast::text($row['Reference'] ?? null),
            Cast::text($row['Notes'] ?? null),
            Cast::text($row['Currency'] ?? null),
            Cast::float($row['FxRate'] ?? null),
            Cast::bool($row['AutoPopulateFxRate'] ?? null),
            Cast::int($row['JournalContactId'] ?? null),
            Cast::text($row['ContactFirstName'] ?? null),
            Cast::text($row['ContactLastName'] ?? null),
            Cast::text($row['ContactOrganisationName'] ?? null),
            Cast::bool($row['RequiresFollowUp'] ?? null),
            Cast::tags($row['Tags'] ?? null),
            Cast::objects($row['Items'] ?? null, JournalItem::fromArray(...)),
            Cast::datetime($row['CreatedDateUtc'] ?? null),
            Cast::datetime($row['LastModifiedDateUtc'] ?? null),
            $row,
        );
    }

    /**
     * Total debits less total credits. Zero for a journal that balances.
     */
    public function imbalance(): float
    {
        $sum = 0.0;

        foreach ($this->items as $item) {
            $sum += $item->signedAmount();
        }

        return round($sum, 2);
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Enum\JournalEntryType;
use Hampel\Saasu\Api\Support\Cast;

/**
 * One line of a journal: an amount, an account, and whether it is a debit or a credit.
 */
final class JournalItem extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?int $accountId = null,
        public readonly ?string $taxCode = null,
        public readonly ?float $amount = null,
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
            Cast::text($row['Type'] ?? null),
            Cast::int($row['AccountId'] ?? null),
            Cast::text($row['TaxCode'] ?? null),
            Cast::float($row['Amount'] ?? null),
            $row,
        );
    }

    public function entryType(): ?JournalEntryType
    {
        return Cast::enum(JournalEntryType::class, $this->type);
    }

    /**
     * The amount as a debit-positive number.
     */
    public function signedAmount(): float
    {
        return ($this->entryType() === JournalEntryType::Credit ? -1 : 1) * ($this->amount ?? 0.0);
    }
}

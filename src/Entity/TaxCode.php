<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A tax code - `G1`, `GST on Income` - and its rate.
 *
 * `Rate` is a fraction: 0.1 is ten percent. Read-only through this API.
 *
 * `Code` can hold more than one code, comma-separated, as Saasu's own sample shows (`G1,G2`).
 */
final class TaxCode extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $code = null,
        public readonly ?string $name = null,
        public readonly ?float $rate = null,
        public readonly ?int $postingAccountId = null,
        public readonly ?bool $isSale = null,
        public readonly ?bool $isPurchase = null,
        public readonly ?bool $isPayroll = null,
        public readonly ?bool $isInbuilt = null,
        public readonly ?bool $isShared = null,
        public readonly ?bool $isActive = null,
        public readonly ?string $notes = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?int $lastModifiedByUserId = null,
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
            Cast::int($row['Id'] ?? null),
            Cast::text($row['Code'] ?? null),
            Cast::text($row['Name'] ?? null),
            Cast::float($row['Rate'] ?? null),
            Cast::int($row['PostingAccountId'] ?? null),
            Cast::bool($row['IsSale'] ?? null),
            Cast::bool($row['IsPurchase'] ?? null),
            Cast::bool($row['IsPayroll'] ?? null),
            Cast::bool($row['IsInbuilt'] ?? null),
            Cast::bool($row['IsShared'] ?? null),
            Cast::bool($row['IsActive'] ?? null),
            Cast::text($row['Notes'] ?? null),
            Cast::text($row['LastUpdatedId'] ?? null),
            Cast::int($row['LastModifiedByUserId'] ?? null),
            Cast::datetime($row['CreatedDateUtc'] ?? null),
            Cast::datetime($row['LastModifiedDateUtc'] ?? null),
            $row,
        );
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return Cast::tags($this->code);
    }
}

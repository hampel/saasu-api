<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * The company inside a ContactAggregate - a narrower record than Company.
 */
final class AggregateCompany extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $abn = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?string $longDescription = null,
        public readonly ?string $tradingName = null,
        public readonly ?string $companyEmail = null,
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
            Cast::text($row['Name'] ?? null),
            Cast::text($row['Abn'] ?? null),
            Cast::text($row['LastUpdatedId'] ?? null),
            Cast::text($row['LongDescription'] ?? null),
            Cast::text($row['TradingName'] ?? null),
            Cast::text($row['CompanyEmail'] ?? null),
            $row,
        );
    }
}

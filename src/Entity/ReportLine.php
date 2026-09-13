<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * One line of a profit and loss report - an account, or a total.
 *
 * The comparison fields are populated only when the report was asked to compare periods.
 */
final class ReportLine extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $accountId = null,
        public readonly ?string $accountName = null,
        public readonly ?float $amount = null,
        public readonly ?float $comparisonAmount = null,
        public readonly ?float $change = null,
        public readonly ?float $percentageChange = null,
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
            Cast::int($row['AccountId'] ?? null),
            Cast::text($row['AccountName'] ?? null),
            Cast::float($row['Amount'] ?? null),
            Cast::float($row['ComparisonAmount'] ?? null),
            Cast::float($row['Change'] ?? null),
            Cast::float($row['PercentageChange'] ?? null),
            $row,
        );
    }
}

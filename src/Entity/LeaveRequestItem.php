<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * The hours of leave on one day of a leave request.
 */
final class LeaveRequestItem extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $lineNumber = null,
        public readonly ?\DateTimeImmutable $date = null,
        public readonly ?float $hours = null,
        public readonly ?bool $paid = null,
        public readonly ?int $processedInPayRunId = null,
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
            Cast::int($row['LineNumber'] ?? null),
            Cast::date($row['Date'] ?? null),
            Cast::float($row['Hours'] ?? null),
            Cast::bool($row['IsPaid'] ?? null),
            Cast::int($row['ProcessedInPayRunId'] ?? null),
            $row,
        );
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * Hours of one kind of leave available to an employee.
 */
final class LeaveBalance extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $payItemId = null,
        public readonly ?string $name = null,
        public readonly ?float $hours = null,
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
            Cast::int($row['PayItemId'] ?? null),
            Cast::text($row['Name'] ?? null),
            Cast::float($row['Hours'] ?? null),
            $row,
        );
    }
}

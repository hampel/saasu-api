<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * One line of a stock adjustment. A negative quantity removes stock.
 */
final class AdjustmentItem extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $itemId = null,
        public readonly ?int $accountId = null,
        public readonly ?float $quantity = null,
        public readonly ?float $unitPrice = null,
        public readonly ?float $totalPrice = null,
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
            Cast::int($row['ItemId'] ?? null),
            Cast::int($row['AccountId'] ?? null),
            Cast::float($row['Quantity'] ?? null),
            Cast::float($row['UnitPrice'] ?? null),
            Cast::float($row['TotalPrice'] ?? null),
            $row,
        );
    }
}

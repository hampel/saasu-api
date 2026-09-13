<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * One line of a stock transfer.
 */
final class TransferItem extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $inventoryItemId = null,
        public readonly ?float $quantity = null,
        public readonly ?float $unitCost = null,
        public readonly ?float $lineTotal = null,
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
            Cast::int($row['InventoryItemId'] ?? null),
            Cast::float($row['Quantity'] ?? null),
            Cast::float($row['UnitCost'] ?? null),
            Cast::float($row['LineTotal'] ?? null),
            $row,
        );
    }
}

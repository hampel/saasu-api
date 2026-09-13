<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * One line of an invoice.
 *
 * TWO KINDS, BY LAYOUT. A service invoice's line names an `AccountId` and an amount; an item
 * invoice's line names an `InventoryId` and a quantity and unit price. `TaxCode` is the code -
 * `G1` - not a tax code id, unlike an item's `SaleTaxCodeId`.
 *
 * `Attributes` is legacy and Saasu says it will be removed.
 */
final class InvoiceLineItem extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $description = null,
        public readonly ?int $accountId = null,
        public readonly ?string $taxCode = null,
        public readonly ?float $totalAmount = null,
        public readonly ?float $quantity = null,
        public readonly ?float $unitPrice = null,
        public readonly ?float $percentageDiscount = null,
        public readonly ?int $inventoryId = null,
        public readonly ?string $itemCode = null,
        public readonly ?string $location = null,
        /** @var list<string> */
        public readonly array $tags = [],
        /** @var list<ItemAttribute> */
        public readonly array $attributes = [],
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
            Cast::text($row['Description'] ?? null),
            Cast::int($row['AccountId'] ?? null),
            Cast::text($row['TaxCode'] ?? null),
            Cast::float($row['TotalAmount'] ?? null),
            Cast::float($row['Quantity'] ?? null),
            Cast::float($row['UnitPrice'] ?? null),
            Cast::float($row['PercentageDiscount'] ?? null),
            Cast::int($row['InventoryId'] ?? null),
            Cast::text($row['ItemCode'] ?? null),
            Cast::text($row['Location'] ?? null),
            Cast::tags($row['Tags'] ?? null),
            Cast::objects($row['Attributes'] ?? null, ItemAttribute::fromArray(...)),
            $row,
        );
    }
}

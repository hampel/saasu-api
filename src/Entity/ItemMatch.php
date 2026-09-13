<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * An inventory or combo item found by search - the indexed fields only, not an Item.
 */
final class ItemMatch extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $entityType = null,
        public readonly ?string $type = null,
        public readonly ?string $code = null,
        public readonly ?string $description = null,
        public readonly ?string $supplierItemCode = null,
        public readonly ?bool $isActive = null,
        public readonly ?float $sellingPrice = null,
        public readonly ?float $buyingPrice = null,
        public readonly ?float $stockOnHand = null,
        /** @var list<string> */
        public readonly array $tags = [],
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
            Cast::text($row['EntityType'] ?? null),
            Cast::text($row['Type'] ?? null),
            Cast::text($row['Code'] ?? null),
            Cast::text($row['Description'] ?? null),
            Cast::text($row['SupplierItemCode'] ?? null),
            Cast::bool($row['IsActive'] ?? null),
            Cast::float($row['SellingPrice'] ?? null),
            Cast::float($row['BuyingPrice'] ?? null),
            Cast::float($row['StockOnHand'] ?? null),
            Cast::tags($row['Tags'] ?? null),
            $row,
        );
    }
}

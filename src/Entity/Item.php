<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Enum\ItemType;
use Hampel\Saasu\Api\Support\Cast;

/**
 * An inventory item or a combo item - something sold, bought or stocked.
 *
 * `Type` is `I` for an inventory item and `C` for a combo, which is built from other items
 * listed in `BuildItems` (only on the record read by id).
 *
 * THE TAX CODES HERE ARE IDS - `SaleTaxCodeId` - where an invoice line names a tax CODE. And
 * Saasu refuses an item marked `IsSold` without an income account, reporting it under the
 * internal name `SaleIncomeAccountUid`.
 *
 * `StockOnHand`, `CurrentValue`, `OnOrder` and `Committed` are computed and read-only.
 */
final class Item extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?string $code = null,
        public readonly ?string $description = null,
        public readonly ?string $type = null,
        public readonly ?string $location = null,
        public readonly ?string $notes = null,
        public readonly ?bool $isActive = null,
        public readonly ?bool $isInventoried = null,
        public readonly ?bool $isSold = null,
        public readonly ?bool $isBought = null,
        public readonly ?bool $isVisible = null,
        public readonly ?bool $isVirtual = null,
        public readonly ?string $virtualType = null,
        public readonly ?int $assetAccountId = null,
        public readonly ?int $saleIncomeAccountId = null,
        public readonly ?int $saleTaxCodeId = null,
        public readonly ?int $saleCostOfSalesAccountId = null,
        public readonly ?int $purchaseExpenseAccountId = null,
        public readonly ?int $purchaseTaxCodeId = null,
        public readonly ?float $sellingPrice = null,
        public readonly ?bool $isSellingPriceIncTax = null,
        public readonly ?float $buyingPrice = null,
        public readonly ?bool $isBuyingPriceIncTax = null,
        public readonly ?float $minimumStockLevel = null,
        public readonly ?float $defaultReOrderQuantity = null,
        public readonly ?float $stockOnHand = null,
        public readonly ?float $currentValue = null,
        public readonly ?float $onOrder = null,
        public readonly ?float $committed = null,
        public readonly ?int $primarySupplierContactId = null,
        public readonly ?string $primarySupplierItemCode = null,
        public readonly ?bool $isVoucher = null,
        public readonly ?\DateTimeImmutable $validFrom = null,
        public readonly ?\DateTimeImmutable $validTo = null,
        /** @var list<BuildItem> */
        public readonly array $buildItems = [],
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
            Cast::text($row['LastUpdatedId'] ?? null),
            Cast::text($row['Code'] ?? null),
            Cast::text($row['Description'] ?? null),
            Cast::text($row['Type'] ?? null),
            Cast::text($row['Location'] ?? null),
            Cast::text($row['Notes'] ?? null),
            Cast::bool($row['IsActive'] ?? null),
            Cast::bool($row['IsInventoried'] ?? null),
            Cast::bool($row['IsSold'] ?? null),
            Cast::bool($row['IsBought'] ?? null),
            Cast::bool($row['IsVisible'] ?? null),
            Cast::bool($row['IsVirtual'] ?? null),
            Cast::text($row['VType'] ?? null),
            Cast::int($row['AssetAccountId'] ?? null),
            Cast::int($row['SaleIncomeAccountId'] ?? null),
            Cast::int($row['SaleTaxCodeId'] ?? null),
            Cast::int($row['SaleCoSAccountId'] ?? null),
            Cast::int($row['PurchaseExpenseAccountId'] ?? null),
            Cast::int($row['PurchaseTaxCodeId'] ?? null),
            Cast::float($row['SellingPrice'] ?? null),
            Cast::bool($row['IsSellingPriceIncTax'] ?? null),
            Cast::float($row['BuyingPrice'] ?? null),
            Cast::bool($row['IsBuyingPriceIncTax'] ?? null),
            Cast::float($row['MinimumStockLevel'] ?? null),
            Cast::float($row['DefaultReOrderQuantity'] ?? null),
            Cast::float($row['StockOnHand'] ?? null),
            Cast::float($row['CurrentValue'] ?? null),
            Cast::float($row['OnOrder'] ?? null),
            Cast::float($row['Committed'] ?? null),
            Cast::int($row['PrimarySupplierContactId'] ?? null),
            Cast::text($row['PrimarySupplierItemCode'] ?? null),
            Cast::bool($row['IsVoucher'] ?? null),
            Cast::datetime($row['ValidFrom'] ?? null),
            Cast::datetime($row['ValidTo'] ?? null),
            Cast::objects($row['BuildItems'] ?? null, BuildItem::fromArray(...)),
            Cast::int($row['LastModifiedBy'] ?? null),
            Cast::datetime($row['CreatedDateUtc'] ?? null),
            Cast::datetime($row['LastModifiedDateUtc'] ?? null),
            $row,
        );
    }

    public function itemType(): ?ItemType
    {
        return Cast::enum(ItemType::class, $this->type);
    }

    public function isCombo(): bool
    {
        return $this->itemType() === ItemType::Combo;
    }

    /**
     * Whether stock has fallen to or below the minimum level - only meaningful for an
     * inventoried item with a minimum set.
     */
    public function needsReorder(): bool
    {
        return $this->isInventoried === true
            && $this->minimumStockLevel !== null
            && $this->stockOnHand !== null
            && $this->stockOnHand <= $this->minimumStockLevel;
    }
}

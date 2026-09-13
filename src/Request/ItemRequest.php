<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Enum\ItemType;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Support\Cast;

/**
 * An inventory or combo item to insert, or the changes to one.
 *
 * SAASU'S CONDITIONAL RULES, as its own tests show them refused: an item marked sold needs an
 * income account, and a combo needs at least one build item. sold() and combo() take what each
 * rule needs.
 */
final class ItemRequest extends Payload
{
    protected const WRITABLE = [
        'Code',
        'Description',
        'Type',
        'Location',
        'Notes',
        'IsActive',
        'IsInventoried',
        'IsSold',
        'IsBought',
        'IsVisible',
        'IsVirtual',
        'VType',
        'AssetAccountId',
        'SaleIncomeAccountId',
        'SaleTaxCodeId',
        'SaleCoSAccountId',
        'PurchaseExpenseAccountId',
        'PurchaseTaxCodeId',
        'SellingPrice',
        'IsSellingPriceIncTax',
        'BuyingPrice',
        'IsBuyingPriceIncTax',
        'MinimumStockLevel',
        'DefaultReOrderQuantity',
        'PrimarySupplierContactId',
        'PrimarySupplierItemCode',
        'IsVoucher',
        'ValidFrom',
        'ValidTo',
        'BuildItems',
        'LastUpdatedId',
    ];

    public static function inventory(string $code, string $description): self
    {
        return self::make()
            ->withType(ItemType::Inventory)
            ->withCode(Cast::required($code, 'An item needs a code.'))
            ->withDescription($description);
    }

    /**
     * @param  list<BuildItemRequest>  $components
     */
    public static function combo(string $code, string $description, array $components): self
    {
        if ($components === []) {
            throw new InvalidArgumentException('A combo item needs at least one component.');
        }

        return self::make()
            ->withType(ItemType::Combo)
            ->withCode(Cast::required($code, 'An item needs a code.'))
            ->withDescription($description)
            ->withBuildItems($components);
    }

    /**
     * Mark the item sold, with the income account Saasu requires for a sold item.
     */
    public function sold(int $incomeAccountId, float $sellingPrice, ?int $taxCodeId = null, bool $priceIncludesTax = true): self
    {
        return $this->withSold(true)
            ->withSaleIncomeAccountId(Cast::id($incomeAccountId, 'income account'))
            ->withSellingPrice($sellingPrice)
            ->withSaleTaxCodeId($taxCodeId)
            ->withSellingPriceIncTax($priceIncludesTax);
    }

    public function bought(int $expenseAccountId, float $buyingPrice, ?int $taxCodeId = null, bool $priceIncludesTax = true): self
    {
        return $this->withBought(true)
            ->withPurchaseExpenseAccountId(Cast::id($expenseAccountId, 'expense account'))
            ->withBuyingPrice($buyingPrice)
            ->withPurchaseTaxCodeId($taxCodeId)
            ->withBuyingPriceIncTax($priceIncludesTax);
    }

    public function withCode(?string $code): static
    {
        return $this->with('Code', $code);
    }

    public function withDescription(?string $description): static
    {
        return $this->with('Description', $description);
    }

    public function withType(ItemType|string|null $type): static
    {
        return $this->with('Type', $type instanceof \BackedEnum ? $type->value : $type);
    }

    public function withLocation(?string $location): static
    {
        return $this->with('Location', $location);
    }

    public function withNotes(?string $notes): static
    {
        return $this->with('Notes', $notes);
    }

    public function withActive(?bool $active): static
    {
        return $this->with('IsActive', $active);
    }

    public function withInventoried(?bool $inventoried): static
    {
        return $this->with('IsInventoried', $inventoried);
    }

    public function withSold(?bool $sold): static
    {
        return $this->with('IsSold', $sold);
    }

    public function withBought(?bool $bought): static
    {
        return $this->with('IsBought', $bought);
    }

    public function withVisible(?bool $visible): static
    {
        return $this->with('IsVisible', $visible);
    }

    public function withVirtual(?bool $virtual): static
    {
        return $this->with('IsVirtual', $virtual);
    }

    public function withVirtualType(?string $virtualType): static
    {
        return $this->with('VType', $virtualType);
    }

    public function withAssetAccountId(?int $assetAccountId): static
    {
        return $this->with('AssetAccountId', $assetAccountId);
    }

    public function withSaleIncomeAccountId(?int $saleIncomeAccountId): static
    {
        return $this->with('SaleIncomeAccountId', $saleIncomeAccountId);
    }

    public function withSaleTaxCodeId(?int $saleTaxCodeId): static
    {
        return $this->with('SaleTaxCodeId', $saleTaxCodeId);
    }

    public function withSaleCostOfSalesAccountId(?int $saleCostOfSalesAccountId): static
    {
        return $this->with('SaleCoSAccountId', $saleCostOfSalesAccountId);
    }

    public function withPurchaseExpenseAccountId(?int $purchaseExpenseAccountId): static
    {
        return $this->with('PurchaseExpenseAccountId', $purchaseExpenseAccountId);
    }

    public function withPurchaseTaxCodeId(?int $purchaseTaxCodeId): static
    {
        return $this->with('PurchaseTaxCodeId', $purchaseTaxCodeId);
    }

    public function withSellingPrice(int|float|null $sellingPrice): static
    {
        return $this->with('SellingPrice', $sellingPrice);
    }

    public function withSellingPriceIncTax(?bool $sellingPriceIncTax): static
    {
        return $this->with('IsSellingPriceIncTax', $sellingPriceIncTax);
    }

    public function withBuyingPrice(int|float|null $buyingPrice): static
    {
        return $this->with('BuyingPrice', $buyingPrice);
    }

    public function withBuyingPriceIncTax(?bool $buyingPriceIncTax): static
    {
        return $this->with('IsBuyingPriceIncTax', $buyingPriceIncTax);
    }

    public function withMinimumStockLevel(int|float|null $minimumStockLevel): static
    {
        return $this->with('MinimumStockLevel', $minimumStockLevel);
    }

    public function withDefaultReOrderQuantity(int|float|null $defaultReOrderQuantity): static
    {
        return $this->with('DefaultReOrderQuantity', $defaultReOrderQuantity);
    }

    public function withPrimarySupplierContactId(?int $primarySupplierContactId): static
    {
        return $this->with('PrimarySupplierContactId', $primarySupplierContactId);
    }

    public function withPrimarySupplierItemCode(?string $primarySupplierItemCode): static
    {
        return $this->with('PrimarySupplierItemCode', $primarySupplierItemCode);
    }

    public function withVoucher(?bool $voucher): static
    {
        return $this->with('IsVoucher', $voucher);
    }

    public function withValidFrom(\DateTimeInterface|string|null $validFrom): static
    {
        return $this->putLocalDateTime('ValidFrom', $validFrom);
    }

    public function withValidTo(\DateTimeInterface|string|null $validTo): static
    {
        return $this->putLocalDateTime('ValidTo', $validTo);
    }

    /**
     * @param  list<BuildItemRequest|array<string, mixed>>  $buildItems
     */
    public function withBuildItems(array $buildItems): static
    {
        return $this->putList('BuildItems', $buildItems);
    }

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}

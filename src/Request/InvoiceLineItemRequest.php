<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Support\Cast;

/**
 * One line of an invoice.
 *
 *     InvoiceLineItemRequest::service('Consulting, September', accountId: 1234, amount: 1650.00, taxCode: 'G1')
 *     InvoiceLineItemRequest::item(inventoryId: 30, quantity: 3, unitPrice: 11.50, taxCode: 'G1')
 *
 * A service line suits an invoice with the service layout, an item line the item layout. `TaxCode`
 * is the code, `G1`, not a tax code id.
 */
final class InvoiceLineItemRequest extends Payload
{
    protected const WRITABLE = [
        'Id',
        'Description',
        'AccountId',
        'TaxCode',
        'Quantity',
        'UnitPrice',
        'TotalAmount',
        'PercentageDiscount',
        'InventoryId',
        'ItemCode',
        'Location',
        'Tags',
    ];

    public static function service(string $description, int $accountId, float $amount, ?string $taxCode = null): self
    {
        return self::make()
            ->withDescription($description)
            ->withAccountId(Cast::id($accountId, 'account'))
            ->withTotalAmount($amount)
            ->withTaxCode($taxCode);
    }

    public static function item(int $inventoryId, float $quantity, float $unitPrice, ?string $taxCode = null, ?string $description = null): self
    {
        return self::make()
            ->withInventoryId(Cast::id($inventoryId, 'inventory item'))
            ->withQuantity($quantity)
            ->withUnitPrice($unitPrice)
            ->withTaxCode($taxCode)
            ->withDescription($description);
    }

    public function withId(?int $id): static
    {
        return $this->with('Id', $id);
    }

    public function withDescription(?string $description): static
    {
        return $this->with('Description', $description);
    }

    public function withAccountId(?int $accountId): static
    {
        return $this->with('AccountId', $accountId);
    }

    public function withTaxCode(?string $taxCode): static
    {
        return $this->with('TaxCode', $taxCode);
    }

    public function withQuantity(int|float|null $quantity): static
    {
        return $this->with('Quantity', $quantity);
    }

    public function withUnitPrice(int|float|null $unitPrice): static
    {
        return $this->with('UnitPrice', $unitPrice);
    }

    public function withTotalAmount(int|float|null $totalAmount): static
    {
        return $this->with('TotalAmount', $totalAmount);
    }

    public function withPercentageDiscount(int|float|null $percentageDiscount): static
    {
        return $this->with('PercentageDiscount', $percentageDiscount);
    }

    public function withInventoryId(?int $inventoryId): static
    {
        return $this->with('InventoryId', $inventoryId);
    }

    public function withItemCode(?string $itemCode): static
    {
        return $this->with('ItemCode', $itemCode);
    }

    public function withLocation(?string $location): static
    {
        return $this->with('Location', $location);
    }

    /**
     * @param  list<string>  $tags
     */
    public function withTags(array $tags): static
    {
        return $this->putTags($tags);
    }
}

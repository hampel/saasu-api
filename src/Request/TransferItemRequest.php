<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Support\Cast;

/**
 * One line of a stock transfer. `LineTotal` is recalculated by Saasu when it does not match.
 */
final class TransferItemRequest extends Payload
{
    protected const WRITABLE = [
        'InventoryItemId',
        'Quantity',
        'UnitCost',
        'LineTotal',
    ];

    public static function of(int $inventoryItemId, float $quantity, float $unitCost): self
    {
        return self::make()->withInventoryItemId(Cast::id($inventoryItemId, 'inventory item'))->withQuantity($quantity)->withUnitCost($unitCost);
    }

    public function withInventoryItemId(?int $inventoryItemId): static
    {
        return $this->with('InventoryItemId', $inventoryItemId);
    }

    public function withQuantity(int|float|null $quantity): static
    {
        return $this->with('Quantity', $quantity);
    }

    public function withUnitCost(int|float|null $unitCost): static
    {
        return $this->with('UnitCost', $unitCost);
    }

    public function withLineTotal(int|float|null $lineTotal): static
    {
        return $this->with('LineTotal', $lineTotal);
    }
}

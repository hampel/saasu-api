<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Support\Cast;

/**
 * One line of a stock adjustment. Quantity allows three decimals; a negative one removes stock.
 * `TotalPrice` is recalculated by Saasu when it does not match quantity times unit price.
 */
final class AdjustmentItemRequest extends Payload
{
    protected const WRITABLE = [
        'ItemId',
        'AccountId',
        'Quantity',
        'UnitPrice',
        'TotalPrice',
    ];

    public static function of(int $itemId, int $accountId, float $quantity, float $unitPrice): self
    {
        return self::make()->withItemId(Cast::id($itemId, 'item'))->withAccountId(Cast::id($accountId, 'account'))->withQuantity($quantity)->withUnitPrice($unitPrice);
    }

    public function withItemId(?int $itemId): static
    {
        return $this->with('ItemId', $itemId);
    }

    public function withAccountId(?int $accountId): static
    {
        return $this->with('AccountId', $accountId);
    }

    public function withQuantity(int|float|null $quantity): static
    {
        return $this->with('Quantity', $quantity);
    }

    public function withUnitPrice(int|float|null $unitPrice): static
    {
        return $this->with('UnitPrice', $unitPrice);
    }

    public function withTotalPrice(int|float|null $totalPrice): static
    {
        return $this->with('TotalPrice', $totalPrice);
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Support\Cast;

/**
 * One component of a combo item: another item's id, and how many one combo takes.
 */
final class BuildItemRequest extends Payload
{
    protected const WRITABLE = [
        'Id',
        'Quantity',
    ];

    public static function of(int $itemId, float $quantity): self
    {
        return self::make()->withId(Cast::id($itemId, 'item'))->withQuantity($quantity);
    }

    public function withId(?int $id): static
    {
        return $this->with('Id', $id);
    }

    public function withQuantity(int|float|null $quantity): static
    {
        return $this->with('Quantity', $quantity);
    }
}

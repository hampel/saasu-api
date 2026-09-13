<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * An inventory item, or a combo built from other items.
 */
enum ItemType: string
{
    case Inventory = 'I';
    case Combo = 'C';
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * An item invoice's lines are inventory items with quantities; a service invoice's are accounts with amounts.
 */
enum InvoiceLayout: string
{
    case Item = 'I';
    case Service = 'S';
}

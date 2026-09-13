<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * Quote, order or invoice. Derived by Saasu from the invoice type; a filter, not something to set.
 */
enum InvoiceStatus: string
{
    case Quote = 'Q';
    case Order = 'O';
    case Invoice = 'I';
}

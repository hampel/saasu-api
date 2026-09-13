<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * The invoice-list payment filter. Saasu's default is `A`, all.
 */
enum PaymentStatus: string
{
    case Paid = 'P';
    case Unpaid = 'U';
    case All = 'A';
}

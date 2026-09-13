<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * How a report recognises income and expenses. Saasu's default is accrual.
 */
enum AccountingMethod: string
{
    case Accrual = 'Accrual';
    case Cash = 'Cash';
}

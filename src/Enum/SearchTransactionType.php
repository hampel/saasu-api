<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * Narrows a transaction search to one kind.
 */
enum SearchTransactionType: string
{
    case Sale = 'Transactions.Sale';
    case Purchase = 'Transactions.Purchase';
    case Journal = 'Transactions.Journal';
    case Payroll = 'Transactions.Payroll';
}

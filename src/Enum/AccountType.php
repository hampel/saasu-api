<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * The kind of account, as the chart of accounts groups them.
 */
enum AccountType: string
{
    case Income = 'Income';
    case Expense = 'Expense';
    case Asset = 'Asset';
    case Equity = 'Equity';
    case Liability = 'Liability';
    case OtherIncome = 'OtherIncome';
    case OtherExpense = 'OtherExpense';
    case CostOfSales = 'CostOfSales';
}

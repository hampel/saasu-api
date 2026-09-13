<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * What kind of document an invoice is. The values are Saasu's own, spaces and parentheses included.
 */
enum InvoiceType: string
{
    case PreQuoteOpportunity = 'Pre-Quote Opportunity';
    case Quote = 'Quote';
    case PurchaseOrder = 'Purchase Order';
    case SaleOrder = 'Sale Order';
    case TaxInvoice = 'Tax Invoice';
    case AdjustmentNote = 'Adjustment Note';
    case RctInvoice = 'RCT Invoice';
    case MoneyIn = 'Money In (Income)';
    case MoneyOut = 'Money Out (Expense)';
}

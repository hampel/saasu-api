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

    /**
     * The sale of a file not registered for GST, where a Tax Invoice is refused with a 400
     * "Invalid invoice type: Tax Invoice". Not on Saasu's help pages; booked live 2026-11-20 by a
     * consumer, in a file not registered for GST. What a registered file does with one is
     * unmeasured.
     */
    case SaleInvoice = 'Sale Invoice';
    case AdjustmentNote = 'Adjustment Note';
    case RctInvoice = 'RCT Invoice';
    case MoneyIn = 'Money In (Income)';
    case MoneyOut = 'Money Out (Expense)';
}

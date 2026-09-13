<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * Which document layout an invoice PDF is printed as - Saasu's `PrintAs`, for a file with the new theme on. The numbers are Saasu's.
 */
enum PdfLayout: int
{
    case Sale = 4;
    case Quote = 93;
    case SaleOrder = 99;
    case ShippingSlip = 98;
    case Purchase = 7;
    case PurchaseQuote = 95;
    case PurchaseOrder = 100;
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * Which side of the ledger a payment is on - `SP` against sales, `PP` against purchases.
 */
enum PaymentType: string
{
    case SalePayment = 'SP';
    case PurchasePayment = 'PP';
}

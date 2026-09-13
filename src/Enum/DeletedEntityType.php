<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * The kinds of record the deleted-entities list reports.
 */
enum DeletedEntityType: string
{
    case Sale = 'Sale';
    case Purchase = 'Purchase';
    case SalePayment = 'SalePayment';
    case PurchasePayment = 'PurchasePayment';
    case Item = 'Item';
    case Contact = 'Contact';
    case Journal = 'Journal';
}

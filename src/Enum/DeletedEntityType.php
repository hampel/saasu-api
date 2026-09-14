<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * The kinds of record the deleted-entities list reports.
 *
 * `InventoryTransfer` and `InventoryAdjustment` are not in Saasu's documented list, and were
 * measured on 2026-09-14 for a deleted stock transfer - including the one Saasu creates for a combo
 * build - and a deleted stock adjustment. The list may carry
 * other undocumented types; Entity\DeletedEntity keeps the raw string.
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
    case InventoryTransfer = 'InventoryTransfer';
    case InventoryAdjustment = 'InventoryAdjustment';
}

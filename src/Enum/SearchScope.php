<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * What a search looks through. Saasu takes one scope, and treats an unrecognised one as `All`.
 */
enum SearchScope: string
{
    case All = 'All';
    case Transactions = 'Transactions';
    case Contacts = 'Contacts';
    case InventoryItems = 'InventoryItems';
}

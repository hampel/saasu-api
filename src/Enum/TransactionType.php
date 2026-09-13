<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * Which side of the ledger an invoice is on.
 */
enum TransactionType: string
{
    case Sale = 'S';
    case Purchase = 'P';
}

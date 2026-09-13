<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * A debit or a credit.
 */
enum JournalEntryType: string
{
    case Debit = 'Debit';
    case Credit = 'Credit';
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * What an activity is attached to. Documented capitalised and returned lower case; Cast::enum() matches either.
 */
enum AttachedToType: string
{
    case Contact = 'Contact';
    case Employee = 'Employee';
    case Sale = 'Sale';
    case Purchase = 'Purchase';
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * How the item-list text filter matches code and description.
 */
enum SearchMethod: string
{
    case Contains = 'Contains';
    case StartsWith = 'StartsWith';
}

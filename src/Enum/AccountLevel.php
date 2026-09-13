<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * A header account groups others and cannot be posted to; a detail account can.
 */
enum AccountLevel: string
{
    case Header = 'Header';
    case Detail = 'Detail';
}

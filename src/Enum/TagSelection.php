<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * How a list filter combines the tags it is given.
 */
enum TagSelection: string
{
    case RequireAny = 'requireAny';
    case RequireAll = 'requireAll';
    case ExcludeAny = 'excludeAny';
    case ExcludeAll = 'excludeAll';
}

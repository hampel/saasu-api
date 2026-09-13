<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * Where a leave request stands.
 */
enum LeaveStatus: string
{
    case Pending = 'Pending';
    case Approved = 'Approved';
    case Rejected = 'Rejected';
}

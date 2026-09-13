<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * The activity-list status filter. `overdue` is not done and past due.
 */
enum ActivityStatus: string
{
    case Todo = 'todo';
    case Done = 'done';
    case Overdue = 'overdue';
}

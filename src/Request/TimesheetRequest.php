<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Support\Cast;

/**
 * A timesheet entry to insert, or the changes to one. Times are sent as local wall-clock times -
 * see Cast::localDateTimeString().
 */
final class TimesheetRequest extends Payload
{
    protected const WRITABLE = [
        'ContactId',
        'StartTime',
        'FinishTime',
        'BreakDurationInMinutes',
        'Notes',
        'LastUpdatedId',
    ];

    public static function of(int $employeeId, \DateTimeInterface $start, \DateTimeInterface $finish, int $breakMinutes = 0): self
    {
        if ($finish <= $start) {
            throw new InvalidArgumentException('A timesheet cannot finish before it starts.');
        }

        return self::make()
            ->withEmployeeId(Cast::id($employeeId, 'employee'))
            ->withStartTime($start)
            ->withFinishTime($finish)
            ->withBreakMinutes($breakMinutes);
    }

    public function withEmployeeId(?int $employeeId): static
    {
        return $this->with('ContactId', $employeeId);
    }

    public function withStartTime(\DateTimeInterface|string|null $startTime): static
    {
        return $this->putLocalDateTime('StartTime', $startTime);
    }

    public function withFinishTime(\DateTimeInterface|string|null $finishTime): static
    {
        return $this->putLocalDateTime('FinishTime', $finishTime);
    }

    public function withBreakMinutes(?int $breakMinutes): static
    {
        return $this->with('BreakDurationInMinutes', $breakMinutes);
    }

    public function withNotes(?string $notes): static
    {
        return $this->with('Notes', $notes);
    }

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}

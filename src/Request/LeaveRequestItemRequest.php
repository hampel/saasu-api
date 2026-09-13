<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

/**
 * The hours of leave on one day. For an employee on 38 hours a week, a full day is 7.6.
 */
final class LeaveRequestItemRequest extends Payload
{
    protected const WRITABLE = [
        'Date',
        'Hours',
    ];

    public static function of(\DateTimeInterface|string $date, float $hours): self
    {
        return self::make()->withDate($date)->withHours($hours);
    }

    public function withDate(\DateTimeInterface|string|null $date): static
    {
        return $this->putDate('Date', $date);
    }

    public function withHours(int|float|null $hours): static
    {
        return $this->with('Hours', $hours);
    }
}

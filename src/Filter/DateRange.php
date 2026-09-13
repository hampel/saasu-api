<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Two dates that Saasu will only accept together.
 *
 * EVERY DATE FILTER ON THIS API IS A PAIR, and half of one is not a narrower query - the
 * documentation marks each "must also specify" the other end. So a range is one value with
 * both ends, and half a range cannot be written. Both ends are inclusive, as Saasu documents
 * them ("greater than or equal to", "less than or equal to").
 *
 *     DateRange::between('2026-07-01', '2027-06-30')
 *     DateRange::on($date)
 *     DateRange::financialYear(2027)        // 1 July 2026 to 30 June 2027
 */
final class DateRange
{
    private function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
    }

    public static function between(\DateTimeInterface|string $from, \DateTimeInterface|string $to): self
    {
        $from = Cast::dateString($from);
        $to = Cast::dateString($to);

        if ($from > $to) {
            throw new InvalidArgumentException(sprintf('A date range cannot end (%s) before it starts (%s).', $to, $from));
        }

        return new self($from, $to);
    }

    public static function on(\DateTimeInterface|string $date): self
    {
        return self::between($date, $date);
    }

    /**
     * An Australian financial year, named - as they are - by the year it ends in.
     *
     * Saasu files have a configurable year end, and this assumes the default of 30 June. A file
     * with another year end wants between().
     */
    public static function financialYear(int $endingIn): self
    {
        return new self(sprintf('%04d-07-01', $endingIn - 1), sprintf('%04d-06-30', $endingIn));
    }

    /**
     * @return array<string, string>
     */
    public function toQuery(string $fromName, string $toName): array
    {
        return [$fromName => $this->from, $toName => $this->to];
    }
}

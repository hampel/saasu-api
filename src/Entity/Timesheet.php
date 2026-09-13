<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A block of hours an employee worked.
 *
 * `ContactId` is the employee's contact id, and is `$employeeId` here because that is what it
 * means. Start and finish are local times, kept as Saasu wrote them - see Cast::localDateTime().
 */
final class Timesheet extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?int $employeeId = null,
        public readonly ?\DateTimeImmutable $startTime = null,
        public readonly ?\DateTimeImmutable $finishTime = null,
        public readonly ?int $breakMinutes = null,
        public readonly ?string $notes = null,
        public readonly ?int $lastModifiedByUserId = null,
        public readonly ?\DateTimeImmutable $createdDateUtc = null,
        public readonly ?\DateTimeImmutable $lastModifiedDateUtc = null,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): static
    {
        return new self(
            Cast::int($row['Id'] ?? null),
            Cast::text($row['LastUpdatedId'] ?? null),
            Cast::int($row['ContactId'] ?? null),
            Cast::localDateTime($row['StartTime'] ?? null),
            Cast::localDateTime($row['FinishTime'] ?? null),
            Cast::int($row['BreakDurationInMinutes'] ?? null),
            Cast::text($row['Notes'] ?? null),
            Cast::int($row['LastModifiedByUserId'] ?? null),
            Cast::datetime($row['CreatedDateUtc'] ?? null),
            Cast::datetime($row['LastModifiedDateUtc'] ?? null),
            $row,
        );
    }

    /**
     * Minutes worked, less the break. Null when either end is missing.
     */
    public function workedMinutes(): ?int
    {
        if ($this->startTime === null || $this->finishTime === null) {
            return null;
        }

        $minutes = intdiv($this->finishTime->getTimestamp() - $this->startTime->getTimestamp(), 60);

        return $minutes - ($this->breakMinutes ?? 0);
    }
}

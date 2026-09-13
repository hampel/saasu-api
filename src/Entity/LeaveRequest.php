<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Enum\LeaveStatus;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Leave an employee has asked for, or been granted.
 *
 * NO LastUpdatedId. A leave request's concurrency check is `LastModifiedDateUtc`, which must be
 * sent back on an update exactly as it was read - so it is kept on `raw` as a string, and
 * LeaveRequestRequest::from() carries it over untouched.
 */
final class LeaveRequest extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $employeeId = null,
        public readonly ?int $leaveTypePayItemId = null,
        public readonly ?\DateTimeImmutable $startDate = null,
        public readonly ?\DateTimeImmutable $endDate = null,
        public readonly ?float $totalHours = null,
        public readonly ?string $status = null,
        public readonly ?string $notes = null,
        /** @var list<LeaveRequestItem> */
        public readonly array $items = [],
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
            Cast::int($row['EmployeeId'] ?? null),
            Cast::int($row['LeaveTypePayItemId'] ?? null),
            Cast::date($row['StartDate'] ?? null),
            Cast::date($row['EndDate'] ?? null),
            Cast::float($row['TotalHours'] ?? null),
            Cast::text($row['Status'] ?? null),
            Cast::text($row['Notes'] ?? null),
            Cast::objects($row['Items'] ?? null, LeaveRequestItem::fromArray(...)),
            Cast::datetime($row['CreatedDateUtc'] ?? null),
            Cast::datetime($row['LastModifiedDateUtc'] ?? null),
            $row,
        );
    }

    public function leaveStatus(): ?LeaveStatus
    {
        return Cast::enum(LeaveStatus::class, $this->status);
    }
}

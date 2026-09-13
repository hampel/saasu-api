<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Enum\LeaveStatus;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Support\Cast;

/**
 * A leave request to insert, or the changes to one.
 *
 * ITS CONCURRENCY CHECK IS A TIMESTAMP, NOT AN ID. Saasu requires `LastModifiedDateUtc` on an
 * update, exactly as it was read, so from() carries it over as the string that arrived.
 *
 * Approved leave must carry its day-by-day `Items` - the pay run reads the hours from them.
 */
final class LeaveRequestRequest extends Payload
{
    protected const WRITABLE = [
        'EmployeeId',
        'LeaveTypePayItemId',
        'StartDate',
        'EndDate',
        'Status',
        'Notes',
        'Items',
        'LastModifiedDateUtc',
    ];

    /**
     * The longest leave Saasu documents accepting, in its own words "3 months (63 days)" - which
     * disagree with each other, so this is not enforced here. A rejection invented on this side
     * of the API could not be argued with.
     */
    public const DOCUMENTED_MAX_DAYS = 63;

    public static function of(int $employeeId, int $leaveTypePayItemId, \DateTimeInterface|string $start, \DateTimeInterface|string $end, LeaveStatus $status = LeaveStatus::Pending): self
    {
        $request = self::make()
            ->withEmployeeId(Cast::id($employeeId, 'employee'))
            ->withLeaveTypePayItemId(Cast::id($leaveTypePayItemId, 'leave pay item'))
            ->withStartDate($start)
            ->withEndDate($end)
            ->withStatus($status);

        if (Cast::string($request->get('EndDate')) < Cast::string($request->get('StartDate'))) {
            throw new InvalidArgumentException('Leave cannot end before it starts.');
        }

        return $request;
    }

    public function withEmployeeId(?int $employeeId): static
    {
        return $this->with('EmployeeId', $employeeId);
    }

    public function withLeaveTypePayItemId(?int $leaveTypePayItemId): static
    {
        return $this->with('LeaveTypePayItemId', $leaveTypePayItemId);
    }

    public function withStartDate(\DateTimeInterface|string|null $startDate): static
    {
        return $this->putDate('StartDate', $startDate);
    }

    public function withEndDate(\DateTimeInterface|string|null $endDate): static
    {
        return $this->putDate('EndDate', $endDate);
    }

    public function withStatus(LeaveStatus|string|null $status): static
    {
        return $this->with('Status', $status instanceof \BackedEnum ? $status->value : $status);
    }

    public function withNotes(?string $notes): static
    {
        return $this->with('Notes', $notes);
    }

    /**
     * @param  list<LeaveRequestItemRequest|array<string, mixed>>  $items
     */
    public function withItems(array $items): static
    {
        return $this->putList('Items', $items);
    }
}

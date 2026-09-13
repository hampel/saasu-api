<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\Timesheet;
use Hampel\Saasu\Api\Request\TimesheetRequest;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Timesheet entries, at `Payroll/Timesheet`. There is no list endpoint.
 */
final class Timesheets extends Endpoint
{
    /**
     * One timesheet by id. Raises NotFoundException when there is none.
     */
    public function get(int $id): Timesheet
    {
        return $this->apiObject($this->path($id), Timesheet::fromArray(...));
    }

    /**
     * One timesheet by id, or null.
     */
    public function find(int $id): ?Timesheet
    {
        return $this->apiFind($this->path($id), Timesheet::fromArray(...));
    }

    /**
     * Insert a timesheet. Keep the answer's LastUpdatedId: it is what the next update needs.
     */
    public function create(TimesheetRequest $request): Saved
    {
        return $this->apiSave('POST', 'Payroll/Timesheet', $request);
    }

    /**
     * Update a timesheet.
     *
     * Build the request from() the timesheet as read - it carries the LastUpdatedId Saasu requires,
     * and the fields a PUT might otherwise clear. Refused before sending without the
     * LastUpdatedId; raises ConcurrencyException when the timesheet changed after it was read.
     */
    public function update(int $id, TimesheetRequest $request): Saved
    {
        $this->assertUpdatable($request, 'timesheet');

        return $this->apiSave('PUT', $this->path($id), $request, $id);
    }

    /**
     * Delete a timesheet. Saasu answers with a status message rather than a 204, which is dropped.
     */
    public function delete(int $id): void
    {
        $this->apiDelete($this->path($id));
    }

    private function path(int $id): string
    {
        return 'Payroll/Timesheet/' . Cast::id($id, 'timesheet');
    }
}

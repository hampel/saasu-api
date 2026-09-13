<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\LeaveRequest;
use Hampel\Saasu\Api\Request\LeaveRequestRequest;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Leave requests, at `Payroll/LeaveRequest`. There is no list endpoint.
 *
 * AN UPDATE IS CHECKED BY TIMESTAMP. Saasu requires the `LastModifiedDateUtc` the leave request
 * had when read, not a LastUpdatedId. Saasu's own documentation page for the update fails with a
 * server error, so its shape is inferred from the insert and from the model.
 */
final class LeaveRequests extends Endpoint
{
    /**
     * One leave request by id. Raises NotFoundException when there is none.
     */
    public function get(int $id): LeaveRequest
    {
        return $this->apiObject($this->path($id), LeaveRequest::fromArray(...));
    }

    /**
     * One leave request by id, or null.
     */
    public function find(int $id): ?LeaveRequest
    {
        return $this->apiFind($this->path($id), LeaveRequest::fromArray(...));
    }

    /**
     * Insert a leave request. Keep the answer's LastUpdatedId: it is what the next update needs.
     */
    public function create(LeaveRequestRequest $request): Saved
    {
        return $this->apiSave('POST', 'Payroll/LeaveRequest', $request);
    }

    /**
     * Update a leave request.
     *
     * Build the request from() the leave request as read - it carries the LastModifiedDateUtc Saasu requires,
     * and the fields a PUT might otherwise clear. Refused before sending without the
     * LastModifiedDateUtc; raises ConcurrencyException when the leave request changed after it was read.
     */
    public function update(int $id, LeaveRequestRequest $request): Saved
    {
        $this->assertUpdatable($request, 'leave request', 'LastModifiedDateUtc');

        return $this->apiSave('PUT', $this->path($id), $request, $id);
    }

    /**
     * Delete a leave request. Saasu documents a status message in answer rather than a 204; it is dropped.
     */
    public function delete(int $id): void
    {
        $this->apiDelete($this->path($id));
    }

    private function path(int $id): string
    {
        return 'Payroll/LeaveRequest/' . Cast::id($id, 'leave request');
    }
}

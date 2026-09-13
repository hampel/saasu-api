<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\Activity;
use Hampel\Saasu\Api\Filter\ActivityFilter;
use Hampel\Saasu\Api\Request\ActivityRequest;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Tasks and notes, at `Activity` and `Activities`.
 */
final class Activities extends Endpoint
{
    /**
     * One page of activitys.
     *
     * @return Page<Activity>
     */
    public function list(?ActivityFilter $filter = null, int $page = 1, ?int $pageSize = null): Page
    {
        return $this->apiPaginate('Activities', 'Activities', Activity::fromArray(...), $filter ?? [], $page, $pageSize);
    }

    /**
     * Every matching activity, a page at a time, as far as it is consumed. Each page is a request.
     *
     * @return \Generator<int, Activity>
     */
    public function each(?ActivityFilter $filter = null, ?int $pageSize = null): \Generator
    {
        return $this->apiEach('Activities', 'Activities', Activity::fromArray(...), $filter ?? [], $pageSize);
    }

    /**
     * Every matching activity, as a list - one request per page, so narrow the filter first.
     *
     * @return list<Activity>
     */
    public function all(?ActivityFilter $filter = null): array
    {
        return iterator_to_array($this->each($filter), false);
    }

    /**
     * One activity by id. Raises NotFoundException when there is none.
     */
    public function get(int $id): Activity
    {
        return $this->apiObject($this->path($id), Activity::fromArray(...));
    }

    /**
     * One activity by id, or null.
     */
    public function find(int $id): ?Activity
    {
        return $this->apiFind($this->path($id), Activity::fromArray(...));
    }

    /**
     * Insert an activity. Keep the answer's LastUpdatedId: it is what the next update needs.
     */
    public function create(ActivityRequest $request): Saved
    {
        return $this->apiSave('POST', 'Activity', $request);
    }

    /**
     * Update an activity.
     *
     * Build the request from() the activity as read - it carries the LastUpdatedId Saasu requires,
     * and the fields a PUT might otherwise clear. Refused before sending without the
     * LastUpdatedId; raises ConcurrencyException when the activity changed after it was read.
     */
    public function update(int $id, ActivityRequest $request): Saved
    {
        $this->assertUpdatable($request, 'activity');

        return $this->apiSave('PUT', $this->path($id), $request, $id);
    }

    /**
     * Delete an activity. Saasu documents a status message in answer rather than a 204; it is dropped.
     */
    public function delete(int $id): void
    {
        $this->apiDelete($this->path($id));
    }

    private function path(int $id): string
    {
        return 'Activity/' . Cast::id($id, 'activity');
    }
}

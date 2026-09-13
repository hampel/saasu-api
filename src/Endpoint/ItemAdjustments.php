<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\ItemAdjustment;
use Hampel\Saasu\Api\Filter\ItemAdjustmentFilter;
use Hampel\Saasu\Api\Request\ItemAdjustmentRequest;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Stock adjustments, at `ItemAdjustment` and `ItemAdjustments`.
 */
final class ItemAdjustments extends Endpoint
{
    /**
     * One page of stock adjustments.
     *
     * @return Page<ItemAdjustment>
     */
    public function list(?ItemAdjustmentFilter $filter = null, int $page = 1, ?int $pageSize = null): Page
    {
        return $this->apiPaginate('ItemAdjustments', 'ItemAdjustments', ItemAdjustment::fromArray(...), $filter ?? [], $page, $pageSize);
    }

    /**
     * Every matching stock adjustment, a page at a time, as far as it is consumed. Each page is a request.
     *
     * @return \Generator<int, ItemAdjustment>
     */
    public function each(?ItemAdjustmentFilter $filter = null, ?int $pageSize = null): \Generator
    {
        return $this->apiEach('ItemAdjustments', 'ItemAdjustments', ItemAdjustment::fromArray(...), $filter ?? [], $pageSize);
    }

    /**
     * Every matching stock adjustment, as a list - one request per page, so narrow the filter first.
     *
     * @return list<ItemAdjustment>
     */
    public function all(?ItemAdjustmentFilter $filter = null): array
    {
        return iterator_to_array($this->each($filter), false);
    }

    /**
     * One stock adjustment by id. Raises NotFoundException when there is none.
     */
    public function get(int $id): ItemAdjustment
    {
        return $this->apiObject($this->path($id), ItemAdjustment::fromArray(...));
    }

    /**
     * One stock adjustment by id, or null.
     */
    public function find(int $id): ?ItemAdjustment
    {
        return $this->apiFind($this->path($id), ItemAdjustment::fromArray(...));
    }

    /**
     * Insert a stock adjustment. Keep the answer's LastUpdatedId: it is what the next update needs.
     */
    public function create(ItemAdjustmentRequest $request): Saved
    {
        return $this->apiSave('POST', 'ItemAdjustment', $request);
    }

    /**
     * Update a stock adjustment.
     *
     * Build the request from() the stock adjustment as read - it carries the LastUpdatedId Saasu requires,
     * and the fields a PUT might otherwise clear. Refused before sending without the
     * LastUpdatedId; raises ConcurrencyException when the stock adjustment changed after it was read.
     */
    public function update(int $id, ItemAdjustmentRequest $request): Saved
    {
        $this->assertUpdatable($request, 'stock adjustment');

        return $this->apiSave('PUT', $this->path($id), $request, $id);
    }

    /**
     * Delete a stock adjustment. Saasu documents a status message in answer rather than a 204; it is dropped.
     */
    public function delete(int $id): void
    {
        $this->apiDelete($this->path($id));
    }

    private function path(int $id): string
    {
        return 'ItemAdjustment/' . Cast::id($id, 'stock adjustment');
    }
}

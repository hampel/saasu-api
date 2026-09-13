<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\ItemTransfer;
use Hampel\Saasu\Api\Filter\ItemTransferFilter;
use Hampel\Saasu\Api\Request\ItemTransferRequest;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Stock transfers, at `ItemTransfer` and `ItemTransfers`.
 *
 * The list's key is `Transfers`, not `ItemTransfers` - Saasu's naming.
 */
final class ItemTransfers extends Endpoint
{
    /**
     * One page of stock transfers.
     *
     * @return Page<ItemTransfer>
     */
    public function list(?ItemTransferFilter $filter = null, int $page = 1, ?int $pageSize = null): Page
    {
        return $this->apiPaginate('ItemTransfers', 'Transfers', ItemTransfer::fromArray(...), $filter ?? [], $page, $pageSize);
    }

    /**
     * Every matching stock transfer, a page at a time, as far as it is consumed. Each page is a request.
     *
     * @return \Generator<int, ItemTransfer>
     */
    public function each(?ItemTransferFilter $filter = null, ?int $pageSize = null): \Generator
    {
        return $this->apiEach('ItemTransfers', 'Transfers', ItemTransfer::fromArray(...), $filter ?? [], $pageSize);
    }

    /**
     * Every matching stock transfer, as a list - one request per page, so narrow the filter first.
     *
     * @return list<ItemTransfer>
     */
    public function all(?ItemTransferFilter $filter = null): array
    {
        return iterator_to_array($this->each($filter), false);
    }

    /**
     * One stock transfer by id. Raises NotFoundException when there is none.
     */
    public function get(int $id): ItemTransfer
    {
        return $this->apiObject($this->path($id), ItemTransfer::fromArray(...));
    }

    /**
     * One stock transfer by id, or null.
     */
    public function find(int $id): ?ItemTransfer
    {
        return $this->apiFind($this->path($id), ItemTransfer::fromArray(...));
    }

    /**
     * Insert a stock transfer. Keep the answer's LastUpdatedId: it is what the next update needs.
     */
    public function create(ItemTransferRequest $request): Saved
    {
        return $this->apiSave('POST', 'ItemTransfer', $request);
    }

    /**
     * Update a stock transfer.
     *
     * Build the request from() the stock transfer as read - it carries the LastUpdatedId Saasu requires,
     * and the fields a PUT might otherwise clear. Refused before sending without the
     * LastUpdatedId; raises ConcurrencyException when the stock transfer changed after it was read.
     */
    public function update(int $id, ItemTransferRequest $request): Saved
    {
        $this->assertUpdatable($request, 'stock transfer');

        return $this->apiSave('PUT', $this->path($id), $request, $id);
    }

    /**
     * Delete a stock transfer. Saasu documents a status message in answer rather than a 204; it is dropped.
     */
    public function delete(int $id): void
    {
        $this->apiDelete($this->path($id));
    }

    private function path(int $id): string
    {
        return 'ItemTransfer/' . Cast::id($id, 'stock transfer');
    }
}

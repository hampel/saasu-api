<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\Item;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Filter\ItemFilter;
use Hampel\Saasu\Api\Request\ItemRequest;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Inventory and combo items, at `Item` and `Items`.
 */
final class Items extends Endpoint
{
    /**
     * One page of items.
     *
     * @return Page<Item>
     */
    public function list(?ItemFilter $filter = null, int $page = 1, ?int $pageSize = null): Page
    {
        return $this->apiPaginate('Items', 'Items', Item::fromArray(...), $filter ?? [], $page, $pageSize);
    }

    /**
     * Every matching item, a page at a time, as far as it is consumed. Each page is a request.
     *
     * @return \Generator<int, Item>
     */
    public function each(?ItemFilter $filter = null, ?int $pageSize = null): \Generator
    {
        return $this->apiEach('Items', 'Items', Item::fromArray(...), $filter ?? [], $pageSize);
    }

    /**
     * Every matching item, as a list - one request per page, so narrow the filter first.
     *
     * @return list<Item>
     */
    public function all(?ItemFilter $filter = null): array
    {
        return iterator_to_array($this->each($filter), false);
    }

    /**
     * One item by id. Raises NotFoundException when there is none.
     */
    public function get(int $id): Item
    {
        return $this->apiObject($this->path($id), Item::fromArray(...));
    }

    /**
     * One item by id, or null.
     */
    public function find(int $id): ?Item
    {
        return $this->apiFind($this->path($id), Item::fromArray(...));
    }

    /**
     * Insert a item. Keep the answer's LastUpdatedId: it is what the next update needs.
     */
    public function create(ItemRequest $request): Saved
    {
        return $this->apiSave('POST', 'Item', $request);
    }

    /**
     * Update a item.
     *
     * Build the request from() the item as read - it carries the LastUpdatedId Saasu requires,
     * and the fields a PUT might otherwise clear. Refused before sending without the
     * LastUpdatedId; raises ConcurrencyException when the item changed after it was read.
     */
    public function update(int $id, ItemRequest $request): Saved
    {
        $this->assertUpdatable($request, 'item');

        return $this->apiSave('PUT', $this->path($id), $request, $id);
    }

    /**
     * Delete a item. Saasu answers with a status message rather than a 204, which is dropped.
     */
    public function delete(int $id): void
    {
        $this->apiDelete($this->path($id));
    }

    /**
     * Build a quantity of a combo item from its components, moving stock. Answers with Saasu's
     * status message - "1 combo item(s) have been built."
     */
    public function build(int $id, float $quantity): ?string
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('A combo build needs a positive quantity.');
        }

        return $this->apiPost($this->path($id) . '/build', ['Quantity' => $quantity])->statusMessage();
    }

    private function path(int $id): string
    {
        return 'Item/' . Cast::id($id, 'item');
    }
}

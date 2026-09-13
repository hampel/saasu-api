<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Result;

use Hampel\Saasu\Api\Exception\InvalidArgumentException;

/**
 * One page of a list.
 *
 *     {"Contacts": [...], "_links": [{"rel": "self", ...}, {"rel": "next", ...}]}
 *
 * THERE IS NO TOTAL. A Saasu list says nothing about how many records exist across its pages -
 * no count, no last page - so the only way to know a list is finished is to reach a page with
 * fewer items than were asked for. hasMore() answers exactly that, which means a list whose size
 * is an exact multiple of the page size costs one extra, empty request to finish.
 *
 * THE `next` LINK IS NOT USED TO DECIDE. Every sample in Saasu's documentation carries one,
 * including samples of two items against a page size of 25, so nothing establishes that it is
 * ever absent. It is kept on `links` for anyone who wants it.
 *
 * @template T
 * @implements \IteratorAggregate<int, T>
 */
final class Page implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * "Maximum page size is 100", per every list endpoint's documentation.
     */
    public const MAX_PAGE_SIZE = 100;

    /**
     * What Saasu sends when a request does not say. This package always says - see Config.
     */
    public const SAASU_DEFAULT_PAGE_SIZE = 25;

    /**
     * @param  list<T>  $items
     * @param  int  $page  the page that was asked for
     * @param  int  $pageSize  the page size that was asked for
     * @param  array<string, mixed>  $extra  anything else at the top of the body - the payroll
     *                                       entry list's totals, for one
     */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $pageSize,
        public readonly Links $links = new Links(),
        public readonly array $extra = [],
    ) {
    }

    /**
     * @template TItem
     * @param  callable(array<string, mixed>): TItem  $map
     * @return self<TItem>
     */
    public static function fromResponse(ApiResponse $response, string $key, callable $map, int $page, int $pageSize): self
    {
        $items = [];

        foreach ($response->collection($key) as $row) {
            $items[] = $map($row);
        }

        $extra = $response->object();
        unset($extra[$key], $extra['_links']);

        return new self($items, $page, $pageSize, $response->links(), $extra);
    }

    /**
     * Whether another page may hold more - true when this one came back full.
     */
    public function hasMore(): bool
    {
        return $this->items !== [] && count($this->items) >= $this->pageSize;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * How many items are on this page. There is no total to compare it with.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    /**
     * @return \ArrayIterator<int, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @return list<T>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    public static function assertValidPageSize(int $pageSize): void
    {
        if ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
            throw new InvalidArgumentException(sprintf(
                'Saasu accepts a page size between 1 and %d; %d was asked for.',
                self::MAX_PAGE_SIZE,
                $pageSize
            ));
        }
    }
}

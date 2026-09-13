<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\TaxCode;
use Hampel\Saasu\Api\Filter\TaxCodeFilter;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Tax codes, at `TaxCode` and `TaxCodes`. Read-only.
 */
final class TaxCodes extends Endpoint
{
    /**
     * One page of tax codes.
     *
     * @return Page<TaxCode>
     */
    public function list(?TaxCodeFilter $filter = null, int $page = 1, ?int $pageSize = null): Page
    {
        return $this->apiPaginate('TaxCodes', 'TaxCodes', TaxCode::fromArray(...), $filter ?? [], $page, $pageSize);
    }

    /**
     * Every matching tax code, a page at a time, as far as it is consumed. Each page is a request.
     *
     * @return \Generator<int, TaxCode>
     */
    public function each(?TaxCodeFilter $filter = null, ?int $pageSize = null): \Generator
    {
        return $this->apiEach('TaxCodes', 'TaxCodes', TaxCode::fromArray(...), $filter ?? [], $pageSize);
    }

    /**
     * Every matching tax code, as a list - one request per page, so narrow the filter first.
     *
     * @return list<TaxCode>
     */
    public function all(?TaxCodeFilter $filter = null): array
    {
        return iterator_to_array($this->each($filter), false);
    }

    /**
     * One tax code by id. Raises NotFoundException when there is none.
     */
    public function get(int $id): TaxCode
    {
        return $this->apiObject($this->path($id), TaxCode::fromArray(...));
    }

    /**
     * One tax code by id, or null.
     */
    public function find(int $id): ?TaxCode
    {
        return $this->apiFind($this->path($id), TaxCode::fromArray(...));
    }

    private function path(int $id): string
    {
        return 'TaxCode/' . Cast::id($id, 'tax code');
    }
}

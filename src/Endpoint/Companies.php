<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\Company;
use Hampel\Saasu\Api\Filter\CompanyFilter;
use Hampel\Saasu\Api\Request\CompanyRequest;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Companies - the organisations contacts belong to - at `Company` and `Companies`.
 *
 * To create a customer and their company in one request, see ContactAggregates.
 */
final class Companies extends Endpoint
{
    /**
     * One page of companys.
     *
     * @return Page<Company>
     */
    public function list(?CompanyFilter $filter = null, int $page = 1, ?int $pageSize = null): Page
    {
        return $this->apiPaginate('Companies', 'Companies', Company::fromArray(...), $filter ?? [], $page, $pageSize);
    }

    /**
     * Every matching company, a page at a time, as far as it is consumed. Each page is a request.
     *
     * @return \Generator<int, Company>
     */
    public function each(?CompanyFilter $filter = null, ?int $pageSize = null): \Generator
    {
        return $this->apiEach('Companies', 'Companies', Company::fromArray(...), $filter ?? [], $pageSize);
    }

    /**
     * Every matching company, as a list - one request per page, so narrow the filter first.
     *
     * @return list<Company>
     */
    public function all(?CompanyFilter $filter = null): array
    {
        return iterator_to_array($this->each($filter), false);
    }

    /**
     * One company by id. Raises NotFoundException when there is none.
     */
    public function get(int $id): Company
    {
        return $this->apiObject($this->path($id), Company::fromArray(...));
    }

    /**
     * One company by id, or null.
     */
    public function find(int $id): ?Company
    {
        return $this->apiFind($this->path($id), Company::fromArray(...));
    }

    /**
     * Insert a company. Keep the answer's LastUpdatedId: it is what the next update needs.
     */
    public function create(CompanyRequest $request): Saved
    {
        return $this->apiSave('POST', 'Company', $request);
    }

    /**
     * Update a company.
     *
     * Build the request from() the company as read - it carries the LastUpdatedId Saasu requires,
     * and the fields a PUT might otherwise clear. Refused before sending without the
     * LastUpdatedId; raises ConcurrencyException when the company changed after it was read.
     */
    public function update(int $id, CompanyRequest $request): Saved
    {
        $this->assertUpdatable($request, 'company');

        return $this->apiSave('PUT', $this->path($id), $request, $id);
    }

    /**
     * Delete a company. Saasu answers with a status message rather than a 204, which is dropped.
     */
    public function delete(int $id): void
    {
        $this->apiDelete($this->path($id));
    }

    private function path(int $id): string
    {
        return 'Company/' . Cast::id($id, 'company');
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\DeletedEntity;
use Hampel\Saasu\Api\Filter\DeletedEntityFilter;
use Hampel\Saasu\Api\Result\Page;

/**
 * Records that were deleted, at `DeletedEntities` - the half of a sync that last-modified dates
 * cannot see.
 *
 * WITHOUT A RANGE, SAASU LOOKS BACK 24 HOURS. See DeletedEntityFilter.
 */
final class DeletedEntities extends Endpoint
{
    /**
     * @return Page<DeletedEntity>
     */
    public function list(?DeletedEntityFilter $filter = null, int $page = 1, ?int $pageSize = null): Page
    {
        return $this->apiPaginate('DeletedEntities', 'Entities', DeletedEntity::fromArray(...), $filter ?? [], $page, $pageSize);
    }

    /**
     * @return \Generator<int, DeletedEntity>
     */
    public function each(?DeletedEntityFilter $filter = null, ?int $pageSize = null): \Generator
    {
        return $this->apiEach('DeletedEntities', 'Entities', DeletedEntity::fromArray(...), $filter ?? [], $pageSize);
    }

    /**
     * @return list<DeletedEntity>
     */
    public function all(?DeletedEntityFilter $filter = null): array
    {
        return iterator_to_array($this->each($filter), false);
    }
}

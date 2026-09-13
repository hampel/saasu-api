<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\Journal;
use Hampel\Saasu\Api\Filter\JournalFilter;
use Hampel\Saasu\Api\Request\JournalRequest;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * General journals, at `Journal` and `Journals`.
 */
final class Journals extends Endpoint
{
    /**
     * One page of journals.
     *
     * @return Page<Journal>
     */
    public function list(?JournalFilter $filter = null, int $page = 1, ?int $pageSize = null): Page
    {
        return $this->apiPaginate('Journals', 'Journals', Journal::fromArray(...), $filter ?? [], $page, $pageSize);
    }

    /**
     * Every matching journal, a page at a time, as far as it is consumed. Each page is a request.
     *
     * @return \Generator<int, Journal>
     */
    public function each(?JournalFilter $filter = null, ?int $pageSize = null): \Generator
    {
        return $this->apiEach('Journals', 'Journals', Journal::fromArray(...), $filter ?? [], $pageSize);
    }

    /**
     * Every matching journal, as a list - one request per page, so narrow the filter first.
     *
     * @return list<Journal>
     */
    public function all(?JournalFilter $filter = null): array
    {
        return iterator_to_array($this->each($filter), false);
    }

    /**
     * One journal by id. Raises NotFoundException when there is none.
     */
    public function get(int $id): Journal
    {
        return $this->apiObject($this->path($id), Journal::fromArray(...));
    }

    /**
     * One journal by id, or null.
     */
    public function find(int $id): ?Journal
    {
        return $this->apiFind($this->path($id), Journal::fromArray(...));
    }

    /**
     * Insert a journal. Keep the answer's LastUpdatedId: it is what the next update needs.
     */
    public function create(JournalRequest $request): Saved
    {
        return $this->apiSave('POST', 'Journal', $request);
    }

    /**
     * Update a journal.
     *
     * Build the request from() the journal as read - it carries the LastUpdatedId Saasu requires,
     * and the fields a PUT might otherwise clear. Refused before sending without the
     * LastUpdatedId; raises ConcurrencyException when the journal changed after it was read.
     */
    public function update(int $id, JournalRequest $request): Saved
    {
        $this->assertUpdatable($request, 'journal');

        return $this->apiSave('PUT', $this->path($id), $request, $id);
    }

    /**
     * Delete a journal. Saasu documents a status message in answer rather than a 204; it is dropped.
     */
    public function delete(int $id): void
    {
        $this->apiDelete($this->path($id));
    }

    private function path(int $id): string
    {
        return 'Journal/' . Cast::id($id, 'journal');
    }
}

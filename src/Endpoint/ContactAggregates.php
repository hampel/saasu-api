<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\ContactAggregate;
use Hampel\Saasu\Api\Request\ContactAggregateRequest;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * A contact with its company and contact manager, at `ContactAggregate`.
 *
 * THE ID IS THE CONTACT'S. There is no aggregate id - an aggregate is read and written through
 * the contact it is centred on.
 *
 * A WRITE CAN CHANGE THREE RECORDS. The company and manager are inserted when the request gives
 * them no id and updated when it does. Saved::companyId() and contactManagerId() say which
 * records the write touched.
 */
final class ContactAggregates extends Endpoint
{
    /**
     * One contact aggregate by id. Raises NotFoundException when there is none.
     */
    public function get(int $id): ContactAggregate
    {
        return $this->apiObject($this->path($id), ContactAggregate::fromArray(...));
    }

    /**
     * One contact aggregate by id, or null.
     */
    public function find(int $id): ?ContactAggregate
    {
        return $this->apiFind($this->path($id), ContactAggregate::fromArray(...));
    }

    /**
     * Insert a contact aggregate. Keep the answer's LastUpdatedId: it is what the next update needs.
     */
    public function create(ContactAggregateRequest $request): Saved
    {
        return $this->apiSave('POST', 'ContactAggregate', $request);
    }

    /**
     * Update a contact aggregate.
     *
     * Build the request from() the contact aggregate as read - it carries the LastUpdatedId Saasu requires,
     * and the fields a PUT might otherwise clear. Refused before sending without the
     * LastUpdatedId; raises ConcurrencyException when the contact aggregate changed after it was read.
     */
    public function update(int $id, ContactAggregateRequest $request): Saved
    {
        $this->assertUpdatable($request, 'contact aggregate');

        return $this->apiSave('PUT', $this->path($id), $request, $id);
    }

    private function path(int $id): string
    {
        return 'ContactAggregate/' . Cast::id($id, 'contact aggregate');
    }
}

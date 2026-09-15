<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\Contact;
use Hampel\Saasu\Api\Filter\ContactFilter;
use Hampel\Saasu\Api\Filter\DateRange;
use Hampel\Saasu\Api\Request\ContactRequest;
use Hampel\Saasu\Api\Result\Document;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Contacts - customers, suppliers, contractors, partners - at `Contact` and `Contacts`.
 */
final class Contacts extends Endpoint
{
    /**
     * One page of contacts.
     *
     * @return Page<Contact>
     */
    public function list(?ContactFilter $filter = null, int $page = 1, ?int $pageSize = null): Page
    {
        return $this->apiPaginate('Contacts', 'Contacts', Contact::fromArray(...), $filter ?? [], $page, $pageSize);
    }

    /**
     * Every matching contact, a page at a time, as far as it is consumed. Each page is a request.
     *
     * @return \Generator<int, Contact>
     */
    public function each(?ContactFilter $filter = null, ?int $pageSize = null): \Generator
    {
        return $this->apiEach('Contacts', 'Contacts', Contact::fromArray(...), $filter ?? [], $pageSize);
    }

    /**
     * Every matching contact, as a list - one request per page, so narrow the filter first.
     *
     * @return list<Contact>
     */
    public function all(?ContactFilter $filter = null): array
    {
        return iterator_to_array($this->each($filter), false);
    }

    /**
     * One contact by id. Raises NotFoundException when there is none.
     */
    public function get(int $id): Contact
    {
        return $this->apiObject($this->path($id), Contact::fromArray(...));
    }

    /**
     * One contact by id, or null.
     */
    public function find(int $id): ?Contact
    {
        return $this->apiFind($this->path($id), Contact::fromArray(...));
    }

    /**
     * Insert a contact. Keep the answer's LastUpdatedId: it is what the next update needs.
     */
    public function create(ContactRequest $request): Saved
    {
        return $this->apiSave('POST', 'Contact', $request);
    }

    /**
     * Update a contact.
     *
     * Build the request from() the contact as read - it carries the LastUpdatedId Saasu requires,
     * and the fields a PUT might otherwise clear. Refused before sending without the
     * LastUpdatedId; raises ConcurrencyException when the contact changed after it was read.
     */
    public function update(int $id, ContactRequest $request): Saved
    {
        $this->assertUpdatable($request, 'contact');

        return $this->apiSave('PUT', $this->path($id), $request, $id);
    }

    /**
     * Delete a contact. Saasu documents a status message in answer rather than a 204; it is dropped.
     */
    public function delete(int $id): void
    {
        $this->apiDelete($this->path($id));
    }

    /**
     * The first contact with this exact email address, or null - one request of one item.
     */
    public function firstWithEmail(string $email): ?Contact
    {
        return $this->list(ContactFilter::all()->withEmail($email), 1, 1)->first();
    }

    /**
     * The first contact with this reference in `ContactId`, or null - one request of one item.
     *
     * The match is on the whole value, ignoring case: `XF-12` does not find `XF-123`. It is the way
     * to find a contact by an identifier from another system, because a custom field cannot be
     * filtered or searched on.
     *
     * A REFERENCE IS NOT UNIQUE. Saasu accepts a second contact with one already in use, and this
     * then answers whichever Saasu lists first. Where two would be a mistake worth knowing about,
     * `list(ContactFilter::all()->reference($reference))` shows both.
     */
    public function firstWithReference(string $reference): ?Contact
    {
        return $this->list(ContactFilter::all()->reference($reference), 1, 1)->first();
    }

    /**
     * A contact's statement for a period, as the PDF Saasu generates.
     */
    public function statement(int $id, DateRange $period): Document
    {
        return $this->apiDocument(
            $this->path($id) . '/generate-pdf',
            ['GenerateType' => 'Statement'] + $period->toQuery('FromDate', 'ToDate')
        );
    }

    private function path(int $id): string
    {
        return 'Contact/' . Cast::id($id, 'contact');
    }
}

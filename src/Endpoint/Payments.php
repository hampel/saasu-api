<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\Payment;
use Hampel\Saasu\Api\Filter\PaymentFilter;
use Hampel\Saasu\Api\Request\PaymentRequest;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Payments against sales and purchases, at `Payment` and `Payments`.
 *
 * THE LIST DEFAULTS TO THE LAST MONTH. With no payment date range Saasu answers for the past
 * month only - see PaymentFilter::paidBetween().
 */
final class Payments extends Endpoint
{
    /**
     * One page of payments.
     *
     * @return Page<Payment>
     */
    public function list(?PaymentFilter $filter = null, int $page = 1, ?int $pageSize = null): Page
    {
        return $this->apiPaginate('Payments', 'PaymentTransactions', Payment::fromArray(...), $filter ?? [], $page, $pageSize);
    }

    /**
     * Every matching payment, a page at a time, as far as it is consumed. Each page is a request.
     *
     * @return \Generator<int, Payment>
     */
    public function each(?PaymentFilter $filter = null, ?int $pageSize = null): \Generator
    {
        return $this->apiEach('Payments', 'PaymentTransactions', Payment::fromArray(...), $filter ?? [], $pageSize);
    }

    /**
     * Every matching payment, as a list - one request per page, so narrow the filter first.
     *
     * @return list<Payment>
     */
    public function all(?PaymentFilter $filter = null): array
    {
        return iterator_to_array($this->each($filter), false);
    }

    /**
     * One payment by id. Raises NotFoundException when there is none.
     */
    public function get(int $id): Payment
    {
        return $this->apiObject($this->path($id), Payment::fromArray(...));
    }

    /**
     * One payment by id, or null.
     */
    public function find(int $id): ?Payment
    {
        return $this->apiFind($this->path($id), Payment::fromArray(...));
    }

    /**
     * Insert a payment. Keep the answer's LastUpdatedId: it is what the next update needs.
     */
    public function create(PaymentRequest $request): Saved
    {
        return $this->apiSave('POST', 'Payment', $request);
    }

    /**
     * Update a payment.
     *
     * Build the request from() the payment as read - it carries the LastUpdatedId Saasu requires,
     * and the fields a PUT might otherwise clear. Refused before sending without the
     * LastUpdatedId; raises ConcurrencyException when the payment changed after it was read.
     */
    public function update(int $id, PaymentRequest $request): Saved
    {
        $this->assertUpdatable($request, 'payment');

        return $this->apiSave('PUT', $this->path($id), $request, $id);
    }

    /**
     * Delete a payment. Saasu answers with a status message rather than a 204, which is dropped.
     */
    public function delete(int $id): void
    {
        $this->apiDelete($this->path($id));
    }

    /**
     * Every payment applied to one invoice.
     *
     * @return list<Payment>
     */
    public function forInvoice(int $invoiceId): array
    {
        return $this->all(PaymentFilter::all()->forInvoice(Cast::id($invoiceId, 'invoice')));
    }

    private function path(int $id): string
    {
        return 'Payment/' . Cast::id($id, 'payment');
    }
}

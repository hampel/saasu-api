<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

use Hampel\Saasu\Api\Enum\PaymentType;

/**
 * Filters for `Payments`.
 *
 * WITH NO PAYMENT DATE RANGE, SAASU RETURNS ONLY THE LAST MONTH. That is its documented default,
 * and a list of "every payment" that does not set paidBetween() is a month of them - complete-
 * looking, and wrong for anything older.
 *
 * forInvoice() makes Saasu ignore the transaction type.
 */
final class PaymentFilter extends Filter
{
    /**
     * Records last modified in this range, in UTC - the filter Saasu recommends for a sync.
     */
    public function modifiedBetween(DateRange $range): static
    {
        return $this->withRange('LastModifiedFromDate', 'LastModifiedToDate', $range);
    }

    /**
     * Payments dated in this range. Without it, Saasu answers for the last month only.
     */
    public function paidBetween(DateRange $range): static
    {
        return $this->withRange('PaymentFromDate', 'PaymentToDate', $range);
    }

    public function clearedBetween(DateRange $range): static
    {
        return $this->withRange('ClearedFromDate', 'ClearedToDate', $range);
    }

    public function ofType(PaymentType $type): static
    {
        return $this->withParameter('TransactionType', $type->value);
    }

    /**
     * Payments applied to this invoice. Saasu ignores ofType() when this is set.
     */
    public function forInvoice(int $invoiceId): static
    {
        return $this->withParameter('ForInvoiceId', $invoiceId);
    }

    public function intoAccount(int $bankAccountId): static
    {
        return $this->withParameter('PaymentAccountId', $bankAccountId);
    }
}

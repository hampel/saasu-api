<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

use Hampel\Saasu\Api\Enum\InvoiceStatus;
use Hampel\Saasu\Api\Enum\PaymentStatus;
use Hampel\Saasu\Api\Enum\TagSelection;
use Hampel\Saasu\Api\Enum\TransactionType;

/**
 * Filters for `Invoices`.
 *
 *     InvoiceFilter::sales()->unpaid()->datedBetween(DateRange::financialYear(2027))
 *
 * WITHOUT A TRANSACTION TYPE, SALES AND PURCHASES COME BACK TOGETHER. An unpaid-invoice report
 * that forgets sales() is a report of what the business owes and is owed, added up.
 */
final class InvoiceFilter extends Filter
{
    public static function sales(): static
    {
        return static::all()->ofTransactionType(TransactionType::Sale);
    }

    public static function purchases(): static
    {
        return static::all()->ofTransactionType(TransactionType::Purchase);
    }

    public function ofTransactionType(TransactionType $type): static
    {
        return $this->withParameter('TransactionType', $type->value);
    }

    /**
     * Records last modified in this range, in UTC - the filter Saasu recommends for a sync.
     */
    public function modifiedBetween(DateRange $range): static
    {
        return $this->withRange('LastModifiedFromDate', 'LastModifiedToDate', $range);
    }

    /**
     * Records carrying tags, combined as the selection says. An empty list removes the filter.
     *
     * @param  list<string>  $tags
     */
    public function tagged(array $tags, TagSelection $selection = TagSelection::RequireAny): static
    {
        return $this->withTagFilter($tags, $selection);
    }

    /**
     * Invoices whose transaction date is in this range.
     */
    public function datedBetween(DateRange $range): static
    {
        return $this->withRange('InvoiceFromDate', 'InvoiceToDate', $range);
    }

    /**
     * Quotes, orders or invoices.
     */
    public function status(InvoiceStatus $status): static
    {
        return $this->withParameter('InvoiceStatus', $status->value);
    }

    public function paymentStatus(PaymentStatus $status): static
    {
        return $this->withParameter('PaymentStatus', $status->value);
    }

    public function unpaid(): static
    {
        return $this->paymentStatus(PaymentStatus::Unpaid);
    }

    public function paid(): static
    {
        return $this->paymentStatus(PaymentStatus::Paid);
    }

    public function invoiceNumber(string $number): static
    {
        return $this->withText('InvoiceNumber', $number);
    }

    public function purchaseOrderNumber(string $number): static
    {
        return $this->withText('PurchaseOrderNumber', $number);
    }

    /**
     * Invoices whose billing contact is this contact id. Saasu's summary page calls the parameter `ContactId`; its detail page and its own client send `BillingContactId`, which is what this sends.
     */
    public function billedTo(int $contactId): static
    {
        return $this->withParameter('BillingContactId', $contactId);
    }
}

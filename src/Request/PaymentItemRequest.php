<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Support\Cast;

/**
 * How much of a payment goes to one invoice. Saasu refuses more than the invoice owes.
 */
final class PaymentItemRequest extends Payload
{
    protected const WRITABLE = [
        'InvoiceTransactionId',
        'AmountPaid',
    ];

    public static function of(int $invoiceId, float $amountPaid): self
    {
        return self::make()->withInvoiceId(Cast::id($invoiceId, 'invoice'))->withAmountPaid($amountPaid);
    }

    public function withInvoiceId(?int $invoiceId): static
    {
        return $this->with('InvoiceTransactionId', $invoiceId);
    }

    public function withAmountPaid(int|float|null $amountPaid): static
    {
        return $this->with('AmountPaid', $amountPaid);
    }
}

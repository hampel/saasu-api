<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * How much of one payment went to one invoice.
 */
final class PaymentItem extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $invoiceId = null,
        public readonly ?float $amountPaid = null,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): static
    {
        return new self(
            Cast::int($row['InvoiceTransactionId'] ?? null),
            Cast::float($row['AmountPaid'] ?? null),
            $row,
        );
    }
}

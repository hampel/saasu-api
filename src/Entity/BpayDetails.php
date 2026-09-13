<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A contact's BPAY biller code and customer reference number.
 */
final class BpayDetails extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?string $billerCode = null,
        public readonly ?string $crn = null,
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
            Cast::text($row['BillerCode'] ?? null),
            Cast::text($row['CRN'] ?? null),
            $row,
        );
    }
}

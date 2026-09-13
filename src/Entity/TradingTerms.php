<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Enum\TradingTermsIntervalType;
use Hampel\Saasu\Api\Enum\TradingTermsType;
use Hampel\Saasu\Api\Support\Cast;

/**
 * When a contact's invoices fall due - "due in 14 days", "end of month plus 30".
 *
 * NUMBERS, NOT NAMES, and not the same shape as an invoice's own terms: see InvoiceTerms, which
 * carries both. The numbers are TradingTermsType and TradingTermsIntervalType cases.
 */
final class TradingTerms extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $type = null,
        public readonly ?int $interval = null,
        public readonly ?int $intervalType = null,
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
            Cast::int($row['TradingTermsType'] ?? null),
            Cast::int($row['TradingTermsInterval'] ?? null),
            Cast::int($row['TradingTermsIntervalType'] ?? null),
            $row,
        );
    }

    public function termsType(): ?TradingTermsType
    {
        return $this->type === null ? null : TradingTermsType::tryFrom($this->type);
    }

    public function termsIntervalType(): ?TradingTermsIntervalType
    {
        return $this->intervalType === null ? null : TradingTermsIntervalType::tryFrom($this->intervalType);
    }
}

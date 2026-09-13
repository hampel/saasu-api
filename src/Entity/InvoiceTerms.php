<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Enum\TradingTermsIntervalType;
use Hampel\Saasu\Api\Enum\TradingTermsType;
use Hampel\Saasu\Api\Support\Cast;

/**
 * An invoice's own trading terms.
 *
 * BOTH A NUMBER AND A NAME, which may disagree: Saasu's own sample has `IntervalType: null`
 * beside `IntervalTypeEnum: "Week"`. termsType() and termsIntervalType() read the number and
 * fall back to the name.
 */
final class InvoiceTerms extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $type = null,
        public readonly ?int $interval = null,
        public readonly ?int $intervalType = null,
        public readonly ?string $typeName = null,
        public readonly ?string $intervalTypeName = null,
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
            Cast::int($row['Type'] ?? null),
            Cast::int($row['Interval'] ?? null),
            Cast::int($row['IntervalType'] ?? null),
            Cast::text($row['TypeEnum'] ?? null),
            Cast::text($row['IntervalTypeEnum'] ?? null),
            $row,
        );
    }

    public function termsType(): ?TradingTermsType
    {
        return ($this->type === null ? null : TradingTermsType::tryFrom($this->type))
            ?? TradingTermsType::fromName($this->typeName);
    }

    public function termsIntervalType(): ?TradingTermsIntervalType
    {
        return ($this->intervalType === null ? null : TradingTermsIntervalType::tryFrom($this->intervalType))
            ?? TradingTermsIntervalType::fromName($this->intervalTypeName);
    }
}

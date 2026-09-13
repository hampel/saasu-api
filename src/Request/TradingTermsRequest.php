<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Enum\TradingTermsIntervalType;
use Hampel\Saasu\Api\Enum\TradingTermsType;

/**
 * A contact's trading terms, as numbers.
 *
 *     TradingTermsRequest::dueIn(14, TradingTermsIntervalType::Day)
 *     TradingTermsRequest::endOfMonthPlus(30)
 */
final class TradingTermsRequest extends Payload
{
    protected const WRITABLE = [
        'TradingTermsType',
        'TradingTermsInterval',
        'TradingTermsIntervalType',
    ];

    public static function dueIn(int $interval, TradingTermsIntervalType $unit = TradingTermsIntervalType::Day): self
    {
        return self::make()->withType(TradingTermsType::DueIn)->withInterval($interval)->withIntervalType($unit);
    }

    public static function endOfMonthPlus(int $days): self
    {
        return self::make()->withType(TradingTermsType::DueInEomPlusXDays)->withInterval($days)->withIntervalType(TradingTermsIntervalType::Day);
    }

    public static function cashOnDelivery(): self
    {
        return self::make()->withType(TradingTermsType::CashOnDelivery);
    }

    public function withType(TradingTermsType|int|null $type): static
    {
        return $this->with('TradingTermsType', $type instanceof \BackedEnum ? $type->value : $type);
    }

    public function withInterval(?int $interval): static
    {
        return $this->with('TradingTermsInterval', $interval);
    }

    public function withIntervalType(TradingTermsIntervalType|int|null $intervalType): static
    {
        return $this->with('TradingTermsIntervalType', $intervalType instanceof \BackedEnum ? $intervalType->value : $intervalType);
    }
}

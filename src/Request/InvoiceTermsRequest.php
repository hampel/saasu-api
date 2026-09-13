<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Enum\TradingTermsIntervalType;
use Hampel\Saasu\Api\Enum\TradingTermsType;

/**
 * An invoice's trading terms. Sent as numbers; Saasu answers with names as well.
 */
final class InvoiceTermsRequest extends Payload
{
    protected const WRITABLE = [
        'Type',
        'Interval',
        'IntervalType',
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
        return $this->with('Type', $type instanceof \BackedEnum ? $type->value : $type);
    }

    public function withInterval(?int $interval): static
    {
        return $this->with('Interval', $interval);
    }

    public function withIntervalType(TradingTermsIntervalType|int|null $intervalType): static
    {
        return $this->with('IntervalType', $intervalType instanceof \BackedEnum ? $intervalType->value : $intervalType);
    }
}

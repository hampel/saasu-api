<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * The unit a trading terms interval counts in.
 */
enum TradingTermsIntervalType: int
{
    case Unspecified = 0;
    case Day = 1;
    case Week = 2;
    case Month = 3;
    case CashOnDelivery = 4;
    case Year = 5;

    /**
     * The case for a name as Saasu writes it in an invoice's `TypeEnum` / `IntervalTypeEnum`.
     */
    public static function fromName(?string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($name !== null && strcasecmp($case->name, $name) === 0) {
                return $case;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Enum;

/**
 * How a due date is worked out. `DueInEomPlusXDays` is "end of month plus a number of days".
 */
enum TradingTermsType: int
{
    case Unspecified = 0;
    case DueIn = 1;
    case DueInEomPlusXDays = 2;
    case CashOnDelivery = 3;

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

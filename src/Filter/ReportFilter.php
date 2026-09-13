<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

use Hampel\Saasu\Api\Enum\AccountingMethod;

/**
 * The period and basis for a profit and loss report.
 *
 *     ReportFilter::all()->period(DateRange::financialYear(2027))->comparedTo(DateRange::financialYear(2026))
 *
 * WITH NO PERIOD, SAASU REPORTS THE CURRENT FINANCIAL YEAR; with no method, accrual. A comparison
 * period should be the EARLIER one - Saasu says the main period "should be later than the
 * comparison period", and does not say what happens otherwise.
 */
final class ReportFilter extends Filter
{
    public function period(DateRange $range): static
    {
        return $this->withRange('FromDate', 'ToDate', $range);
    }

    public function comparedTo(DateRange $range): static
    {
        return $this->withRange('ComparisonPeriodFromDate', 'ComparisonPeriodToDate', $range);
    }

    public function method(AccountingMethod $method): static
    {
        return $this->withParameter('AccountingMethod', $method->value);
    }

    public function cashBasis(): static
    {
        return $this->method(AccountingMethod::Cash);
    }
}

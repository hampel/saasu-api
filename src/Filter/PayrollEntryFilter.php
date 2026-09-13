<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

/**
 * Filters for `Payroll/PayrollEntries`, which Saasu sorts newest first.
 */
final class PayrollEntryFilter extends Filter
{
    /**
     * Payroll entries dated in this range.
     */
    public function datedBetween(DateRange $range): static
    {
        return $this->withRange('FromDate', 'ToDate', $range);
    }

    public function employee(int $employeeId): static
    {
        return $this->withParameter('EmployeeId', $employeeId);
    }
}

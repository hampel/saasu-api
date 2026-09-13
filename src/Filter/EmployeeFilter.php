<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

use Hampel\Saasu\Api\Support\Cast;

/**
 * Filters for the payroll employee endpoints - which Saasu lists as valid in prose rather than
 * as parameters, so these are taken from the endpoint description.
 *
 * INACTIVE EMPLOYEES ARE INCLUDED unless active() is set.
 */
final class EmployeeFilter extends Filter
{
    public function active(bool $active = true): static
    {
        return $this->withParameter('IsActive', $active);
    }

    /**
     * Include each employee's leave balances, optionally as at a date.
     */
    public function withLeaveBalances(\DateTimeInterface|string|null $asAt = null): static
    {
        $filter = $this->withParameter('IncludeLeaveBalances', true);

        return $asAt === null ? $filter->withParameter('LeaveBalanceAsAtDate', null) : $filter->withParameter('LeaveBalanceAsAtDate', Cast::dateString($asAt));
    }
}

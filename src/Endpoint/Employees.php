<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\Employee;
use Hampel\Saasu\Api\Entity\PayItem;
use Hampel\Saasu\Api\Filter\EmployeeFilter;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Employees and leave entitlements, at `Payroll/Employee`, `Payroll/Employees` and
 * `Payroll/Entitlements`.
 *
 * THE EMPLOYEE LIST IS NOT DOCUMENTED AS PAGED, and all() sends no page parameters. Whether Saasu
 * truncates it at its default page size of 25 has not been measured: the API has no way to create
 * an employee, and the test file has none.
 */
final class Employees extends Endpoint
{
    /**
     * Every employee, inactive ones included unless the filter says otherwise.
     *
     * @return list<Employee>
     */
    public function all(?EmployeeFilter $filter = null): array
    {
        return Cast::objects(
            $this->apiGet('Payroll/Employees', $filter?->toQuery() ?? [])->value('Employees'),
            Employee::fromArray(...)
        );
    }

    /**
     * One employee - the contact id - with leave balances if the filter asks for them.
     *
     * The record arrives wrapped, `{"Employee": {...}}`, unlike every other single read.
     */
    public function get(int $id, ?EmployeeFilter $filter = null): Employee
    {
        return Employee::fromArray($this->apiGet($this->path($id), $filter?->toQuery() ?? [])->object('Employee'));
    }

    public function find(int $id, ?EmployeeFilter $filter = null): ?Employee
    {
        return $this->apiFind(
            $this->path($id),
            static fn (array $body): Employee => Employee::fromArray(Cast::object($body['Employee'] ?? null)),
            $filter?->toQuery() ?? []
        );
    }

    /**
     * The leave and entitlement pay items - what a leave request's `LeaveTypePayItemId` names.
     *
     * @return list<PayItem>
     */
    public function entitlements(): array
    {
        return Cast::objects($this->apiGet('Payroll/Entitlements')->value('Items'), PayItem::fromArray(...));
    }

    private function path(int $id): string
    {
        return 'Payroll/Employee/' . Cast::id($id, 'employee');
    }
}

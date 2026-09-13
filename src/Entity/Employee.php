<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * An employee, as payroll sees them.
 *
 * AN EMPLOYEE IS A CONTACT. `Id` is the contact id - it is what a timesheet's `ContactId` and a
 * leave request's `EmployeeId` both name.
 *
 * `LeaveBalances` is empty unless the request asked for balances.
 */
final class Employee extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $displayName = null,
        public readonly ?bool $isActive = null,
        public readonly ?int $employmentTypeId = null,
        public readonly ?string $employmentType = null,
        public readonly ?int $payFrequencyId = null,
        public readonly ?string $payFrequency = null,
        public readonly ?int $payGroupId = null,
        public readonly ?string $payGroupName = null,
        public readonly ?\DateTimeImmutable $lastPaid = null,
        /** @var list<LeaveBalance> */
        public readonly array $leaveBalances = [],
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
            Cast::int($row['Id'] ?? null),
            Cast::text($row['FirstName'] ?? null),
            Cast::text($row['LastName'] ?? null),
            Cast::text($row['DisplayName'] ?? null),
            Cast::bool($row['IsActive'] ?? null),
            Cast::int($row['EmploymentTypeId'] ?? null),
            Cast::text($row['EmploymentType'] ?? null),
            Cast::int($row['PayFrequencyId'] ?? null),
            Cast::text($row['PayFrequency'] ?? null),
            Cast::int($row['AssignedToPayGroupId'] ?? null),
            Cast::text($row['PayGroupName'] ?? null),
            Cast::date($row['LastPaid'] ?? null),
            Cast::objects($row['LeaveBalances'] ?? null, LeaveBalance::fromArray(...)),
            $row,
        );
    }
}

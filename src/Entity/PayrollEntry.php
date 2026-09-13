<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * One employee's pay in one pay run - what a payslip is generated from.
 *
 * Documented only by a generated sample; `Status` is a number whose meanings Saasu does not list.
 * Its sample shows `NetPay` negative and `Taxes` positive, so do not infer a sign convention from
 * it.
 */
final class PayrollEntry extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $payRunId = null,
        public readonly ?int $status = null,
        public readonly ?\DateTimeImmutable $date = null,
        public readonly ?Employee $employee = null,
        public readonly ?float $wages = null,
        public readonly ?float $deductions = null,
        public readonly ?float $taxes = null,
        public readonly ?float $netPay = null,
        public readonly ?float $super = null,
        public readonly ?bool $empty = null,
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
            Cast::int($row['PayRunId'] ?? null),
            Cast::int($row['Status'] ?? null),
            Cast::date($row['Date'] ?? null),
            Cast::nested($row['Employee'] ?? null, Employee::fromArray(...)),
            Cast::float($row['Wages'] ?? null),
            Cast::float($row['Deductions'] ?? null),
            Cast::float($row['Taxes'] ?? null),
            Cast::float($row['NetPay'] ?? null),
            Cast::float($row['Super'] ?? null),
            Cast::bool($row['IsEmpty'] ?? null),
            $row,
        );
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * Sales totals for a period, from `Invoices/SalesStatsSummary`.
 *
 * Documented only by a generated sample of numbered placeholders - the model page does not
 * render - so what each total counts is read from its name, and one thing is measured:
 *
 * FOREIGN-CURRENCY INVOICES ARE ADDED AT FACE VALUE. On 2026-09-14 sales of AUD 110, AUD 25 and
 * USD 100 gave a `Total` of 235 - the USD amount unconverted - while the profit and loss report for
 * the same day converted it. In a multi-currency file these totals mix currencies.
 */
final class SalesSummary extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?float $total = null,
        public readonly ?float $totalOwed = null,
        public readonly ?float $paid = null,
        public readonly ?float $unpaid = null,
        public readonly ?float $overdue = null,
        public readonly ?float $notDueYet = null,
        public readonly ?float $deposited = null,
        public readonly ?float $notDeposited = null,
        public readonly ?\DateTimeImmutable $fromDate = null,
        public readonly ?\DateTimeImmutable $toDate = null,
        public readonly ?int $totalCount = null,
        public readonly ?int $totalPaidCount = null,
        public readonly ?int $totalUnpaidCount = null,
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
            Cast::float($row['Total'] ?? null),
            Cast::float($row['TotalOwed'] ?? null),
            Cast::float($row['Paid'] ?? null),
            Cast::float($row['Unpaid'] ?? null),
            Cast::float($row['Overdue'] ?? null),
            Cast::float($row['NotDueYet'] ?? null),
            Cast::float($row['Deposited'] ?? null),
            Cast::float($row['NotDeposited'] ?? null),
            Cast::date($row['FromDate'] ?? null),
            Cast::date($row['ToDate'] ?? null),
            Cast::int($row['TotalCount'] ?? null),
            Cast::int($row['TotalPaidCount'] ?? null),
            Cast::int($row['TotalUnpaidCount'] ?? null),
            $row,
        );
    }
}

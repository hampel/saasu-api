<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A profit and loss summary.
 *
 * The same class answers both report endpoints. The by-account-type report carries only the
 * totals, so its per-account lists are empty.
 */
final class ProfitAndLoss extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        /** @var list<ReportLine> */
        public readonly array $incomes = [],
        /** @var list<ReportLine> */
        public readonly array $costOfSales = [],
        /** @var list<ReportLine> */
        public readonly array $expenses = [],
        /** @var list<ReportLine> */
        public readonly array $otherIncomes = [],
        /** @var list<ReportLine> */
        public readonly array $otherExpenses = [],
        public readonly ?ReportLine $totalIncome = null,
        public readonly ?ReportLine $totalCostOfSales = null,
        public readonly ?ReportLine $totalExpense = null,
        public readonly ?ReportLine $totalOtherIncome = null,
        public readonly ?ReportLine $totalOtherExpense = null,
        public readonly ?ReportLine $grossProfit = null,
        public readonly ?ReportLine $operatingProfit = null,
        public readonly ?ReportLine $netProfit = null,
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
            Cast::objects($row['Incomes'] ?? null, ReportLine::fromArray(...)),
            Cast::objects($row['CostOfSales'] ?? null, ReportLine::fromArray(...)),
            Cast::objects($row['Expenses'] ?? null, ReportLine::fromArray(...)),
            Cast::objects($row['OtherIncomes'] ?? null, ReportLine::fromArray(...)),
            Cast::objects($row['OtherExpenses'] ?? null, ReportLine::fromArray(...)),
            Cast::nested($row['TotalIncome'] ?? null, ReportLine::fromArray(...)),
            Cast::nested($row['TotalCostOfSales'] ?? null, ReportLine::fromArray(...)),
            Cast::nested($row['TotalExpense'] ?? null, ReportLine::fromArray(...)),
            Cast::nested($row['TotalOtherIncome'] ?? null, ReportLine::fromArray(...)),
            Cast::nested($row['TotalOtherExpense'] ?? null, ReportLine::fromArray(...)),
            Cast::nested($row['GrossProfit'] ?? null, ReportLine::fromArray(...)),
            Cast::nested($row['OperatingProfit'] ?? null, ReportLine::fromArray(...)),
            Cast::nested($row['NetProfit'] ?? null, ReportLine::fromArray(...)),
            $row,
        );
    }
}

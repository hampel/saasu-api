<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\ProfitAndLoss;
use Hampel\Saasu\Api\Filter\ReportFilter;

/**
 * Reports, at `Reports/*`. Profit and loss is the only one Saasu exposes.
 *
 * WITH NO PERIOD, SAASU REPORTS THE CURRENT FINANCIAL YEAR. See ReportFilter.
 */
final class Reports extends Endpoint
{
    /**
     * Profit and loss, account by account.
     */
    public function profitAndLoss(?ReportFilter $filter = null): ProfitAndLoss
    {
        return ProfitAndLoss::fromArray($this->apiGet('Reports/ProfitAndLoss/Summary', $filter?->toQuery() ?? [])->object());
    }

    /**
     * Profit and loss as totals by account type only.
     */
    public function profitAndLossByAccountType(?ReportFilter $filter = null): ProfitAndLoss
    {
        return ProfitAndLoss::fromArray(
            $this->apiGet('Reports/ProfitAndLoss/SummaryByAccountType', $filter?->toQuery() ?? [])->object()
        );
    }
}

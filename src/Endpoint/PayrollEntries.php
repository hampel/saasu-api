<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\PayrollEntry;
use Hampel\Saasu\Api\Filter\PayrollEntryFilter;
use Hampel\Saasu\Api\Result\Document;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Pay run entries and payslips, at `Payroll/PayrollEntries` and `Payroll/Payslip`.
 *
 * An employee self-service login sees only its own entries.
 *
 * THE LIST CARRIES TOTALS - `TotalWages`, `TotalNetPay` and so on - beside the entries. They are
 * on Page::$extra. Whether they total the page or the whole filter, Saasu does not say.
 */
final class PayrollEntries extends Endpoint
{
    /**
     * @return Page<PayrollEntry>
     */
    public function list(?PayrollEntryFilter $filter = null, int $page = 1, ?int $pageSize = null): Page
    {
        return $this->apiPaginate('Payroll/PayrollEntries', 'PayrollEntries', PayrollEntry::fromArray(...), $filter ?? [], $page, $pageSize);
    }

    /**
     * @return \Generator<int, PayrollEntry>
     */
    public function each(?PayrollEntryFilter $filter = null, ?int $pageSize = null): \Generator
    {
        return $this->apiEach('Payroll/PayrollEntries', 'PayrollEntries', PayrollEntry::fromArray(...), $filter ?? [], $pageSize);
    }

    /**
     * @return list<PayrollEntry>
     */
    public function all(?PayrollEntryFilter $filter = null): array
    {
        return iterator_to_array($this->each($filter), false);
    }

    /**
     * A payslip, as the PDF Saasu generates, by payroll entry id.
     */
    public function payslip(int $payrollEntryId): Document
    {
        return $this->apiDocument('Payroll/Payslip/' . Cast::id($payrollEntryId, 'payroll entry') . '/generate-pdf');
    }
}

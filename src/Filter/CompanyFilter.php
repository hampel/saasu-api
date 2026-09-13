<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

/**
 * Filters for `Companies`.
 */
final class CompanyFilter extends Filter
{
    /**
     * Records last modified in this range, in UTC - the filter Saasu recommends for a sync.
     */
    public function modifiedBetween(DateRange $range): static
    {
        return $this->withRange('LastModifiedFromDate', 'LastModifiedToDate', $range);
    }

    public function named(string $name): static
    {
        return $this->withText('CompanyName', $name);
    }
}

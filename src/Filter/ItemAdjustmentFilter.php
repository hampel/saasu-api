<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

use Hampel\Saasu\Api\Enum\TagSelection;

/**
 * Filters for `ItemAdjustments`.
 *
 * A DATE RANGE FAR INTO THE FUTURE IS REFUSED. Measured 2026-09-14: a ToDate of 2099-12-31 answered
 * a bodiless 400, where a ToDate ten years out answered normally - and the same 2099 date is
 * accepted by the invoice and transfer lists. Keep ranges to real dates.
 */
final class ItemAdjustmentFilter extends Filter
{
    /**
     * Records last modified in this range, in UTC - the filter Saasu recommends for a sync.
     */
    public function modifiedBetween(DateRange $range): static
    {
        return $this->withRange('LastModifiedFromDate', 'LastModifiedToDate', $range);
    }

    /**
     * Records carrying tags, combined as the selection says. An empty list removes the filter.
     *
     * @param  list<string>  $tags
     */
    public function tagged(array $tags, TagSelection $selection = TagSelection::RequireAny): static
    {
        return $this->withTagFilter($tags, $selection);
    }

    /**
     * Adjustments dated in this range.
     */
    public function datedBetween(DateRange $range): static
    {
        return $this->withRange('FromDate', 'ToDate', $range);
    }
}

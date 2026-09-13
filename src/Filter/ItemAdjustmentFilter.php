<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

use Hampel\Saasu\Api\Enum\TagSelection;

/**
 * Filters for `ItemAdjustments`.
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

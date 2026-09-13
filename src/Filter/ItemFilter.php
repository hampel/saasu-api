<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

use Hampel\Saasu\Api\Enum\ItemType;
use Hampel\Saasu\Api\Enum\SearchMethod;

/**
 * Filters for `Items`.
 */
final class ItemFilter extends Filter
{
    /**
     * Records last modified in this range, in UTC - the filter Saasu recommends for a sync.
     */
    public function modifiedBetween(DateRange $range): static
    {
        return $this->withRange('LastModifiedFromDate', 'LastModifiedToDate', $range);
    }

    public function active(bool $active = true): static
    {
        return $this->withParameter('IsActive', $active);
    }

    public function ofType(ItemType $type): static
    {
        return $this->withParameter('ItemType', $type->value);
    }

    /**
     * Items whose code or description matches. Saasu accepts at most 128 characters.
     */
    public function search(string $text, SearchMethod $method = SearchMethod::Contains): static
    {
        return $this->withText('SearchText', $text, 128)->withParameter('SearchMethod', $method->value);
    }
}

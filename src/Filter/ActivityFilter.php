<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

use Hampel\Saasu\Api\Enum\ActivityStatus;
use Hampel\Saasu\Api\Enum\TagSelection;

/**
 * Filters for `Activities`.
 */
final class ActivityFilter extends Filter
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
     * Activities dated in this range.
     */
    public function datedBetween(DateRange $range): static
    {
        return $this->withRange('FromDate', 'ToDate', $range);
    }

    public function status(ActivityStatus $status): static
    {
        return $this->withParameter('ActivityStatus', $status->value);
    }

    /**
     * An activity type is any tag in the file with the activity flag set.
     */
    public function ofType(string $activityType): static
    {
        return $this->withText('ActivityType', $activityType);
    }

    /**
     * Activities owned by the user with this login email.
     */
    public function ownedBy(string $email): static
    {
        return $this->withText('OwnerEmail', $email);
    }

    /**
     * Text to look for. Saasu accepts at most 128 characters.
     */
    public function search(string $text): static
    {
        return $this->withText('SearchText', $text, 128);
    }
}

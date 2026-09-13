<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

use Hampel\Saasu\Api\Enum\DeletedEntityType;

/**
 * Filters for `DeletedEntities`.
 *
 * WITH NO RANGE, SAASU LOOKS BACK 24 HOURS - not forever. A sync that runs less often than daily
 * and does not set deletedBetween() misses deletions, silently.
 */
final class DeletedEntityFilter extends Filter
{
    public function ofType(DeletedEntityType $type): static
    {
        return $this->withParameter('EntityType', $type->value);
    }

    /**
     * Deletions in this range, in UTC.
     */
    public function deletedBetween(DateRange $range): static
    {
        return $this->withRange('UtcDeletedFromDate', 'UtcDeletedToDate', $range);
    }
}

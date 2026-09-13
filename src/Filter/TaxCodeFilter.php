<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

/**
 * Filters for `TaxCodes`.
 */
final class TaxCodeFilter extends Filter
{
    public function active(bool $active = true): static
    {
        return $this->withParameter('IsActive', $active);
    }
}

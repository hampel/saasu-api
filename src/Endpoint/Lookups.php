<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\Lookup;
use Hampel\Saasu\Api\Support\Cast;

/**
 * Reference data, at `LookupData/*` - the values some fields must be drawn from.
 *
 * Each call is a request, and none of this changes often: an application that reads these more
 * than occasionally should cache them.
 */
final class Lookups extends Endpoint
{
    /**
     * @return list<Lookup>
     */
    public function countries(): array
    {
        return $this->lookup('Countries');
    }

    /**
     * Keyed by currency code.
     *
     * @return list<Lookup>
     */
    public function currencies(): array
    {
        return $this->lookup('Currencies');
    }

    /**
     * Tax zones - the jurisdictions a file can be set up in.
     *
     * @return list<Lookup>
     */
    public function zones(): array
    {
        return $this->lookup('Zones');
    }

    /**
     * What a file identity's `IndustryTypeId` names.
     *
     * @return list<Lookup>
     */
    public function industryTypes(): array
    {
        return $this->lookup('IndustryTypes');
    }

    /**
     * @return list<Lookup>
     */
    private function lookup(string $name): array
    {
        return Cast::objects($this->apiGet('LookupData/' . $name)->value('Data'), Lookup::fromArray(...));
    }
}

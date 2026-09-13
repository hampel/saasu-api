<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\Brand;
use Hampel\Saasu\Api\Support\Cast;

/**
 * The file's brands, at `Brands`. Read-only, and not paged.
 */
final class Brands extends Endpoint
{
    /**
     * @return list<Brand>
     */
    public function all(): array
    {
        return Cast::objects($this->apiGet('Brands')->value('Brands'), Brand::fromArray(...));
    }

    public function default(): ?Brand
    {
        foreach ($this->all() as $brand) {
            if ($brand->isDefault === true) {
                return $brand;
            }
        }

        return null;
    }
}

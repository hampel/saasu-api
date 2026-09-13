<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A postal address. `Country` is a name, not a code.
 */
final class Address extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?string $street = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $postcode = null,
        public readonly ?string $country = null,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): static
    {
        return new self(
            Cast::text($row['Street'] ?? null),
            Cast::text($row['City'] ?? null),
            Cast::text($row['State'] ?? null),
            Cast::text($row['Postcode'] ?? null),
            Cast::text($row['Country'] ?? null),
            $row,
        );
    }

    public function isEmpty(): bool
    {
        return $this->street === null && $this->city === null && $this->state === null
            && $this->postcode === null && $this->country === null;
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

/**
 * A postal address. `Country` is a name - see Endpoint\Lookups::countries().
 */
final class AddressRequest extends Payload
{
    protected const WRITABLE = [
        'Street',
        'City',
        'State',
        'Postcode',
        'Country',
    ];

    public static function of(?string $street, ?string $city, ?string $state = null, ?string $postcode = null, ?string $country = null): self
    {
        return self::make()->withStreet($street)->withCity($city)->withState($state)->withPostcode($postcode)->withCountry($country);
    }

    public function withStreet(?string $street): static
    {
        return $this->with('Street', $street);
    }

    public function withCity(?string $city): static
    {
        return $this->with('City', $city);
    }

    public function withState(?string $state): static
    {
        return $this->with('State', $state);
    }

    public function withPostcode(?string $postcode): static
    {
        return $this->with('Postcode', $postcode);
    }

    public function withCountry(?string $country): static
    {
        return $this->with('Country', $country);
    }
}

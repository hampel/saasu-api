<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A brand - the identity an invoice is printed and emailed under, for a file trading under more
 * than one name. An invoice names one by `BrandId`; null or 0 is the default brand.
 */
final class Brand extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?bool $isDefault = null,
        public readonly ?bool $useContactDetailsInFileIdentity = null,
        public readonly ?string $websiteUrl = null,
        public readonly ?string $emailAddress = null,
        public readonly ?string $primaryPhone = null,
        public readonly ?string $street = null,
        public readonly ?string $city = null,
        public readonly ?string $postCode = null,
        public readonly ?int $countryId = null,
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
            Cast::int($row['Id'] ?? null),
            Cast::text($row['Name'] ?? null),
            Cast::bool($row['IsDefault'] ?? null),
            Cast::bool($row['UseContactDetailsInFileIdentity'] ?? null),
            Cast::text($row['WebsiteUrl'] ?? null),
            Cast::text($row['EmailAddress'] ?? null),
            Cast::text($row['PrimaryPhone'] ?? null),
            Cast::text($row['Street'] ?? null),
            Cast::text($row['City'] ?? null),
            Cast::text($row['PostCode'] ?? null),
            Cast::int($row['CountryId'] ?? null),
            $row,
        );
    }
}

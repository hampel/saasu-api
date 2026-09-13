<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A contact found by search - the indexed fields only, not a Contact.
 */
final class ContactMatch extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $entityType = null,
        public readonly ?string $name = null,
        public readonly ?int $companyId = null,
        public readonly ?string $company = null,
        public readonly ?string $tradingName = null,
        public readonly ?string $contactReference = null,
        public readonly ?string $contactEmail = null,
        public readonly ?string $mainPhone = null,
        public readonly ?string $secondaryPhone = null,
        public readonly ?string $mobilePhone = null,
        public readonly ?string $twitterId = null,
        public readonly ?string $manager = null,
        public readonly ?bool $isEmployee = null,
        public readonly ?bool $isActive = null,
        /** @var list<string> */
        public readonly array $tags = [],
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
            Cast::text($row['EntityType'] ?? null),
            Cast::text($row['Name'] ?? null),
            Cast::int($row['CompanyId'] ?? null),
            Cast::text($row['Company'] ?? null),
            Cast::text($row['TradingName'] ?? null),
            Cast::text($row['CustomerContactId'] ?? null),
            Cast::text($row['ContactEmail'] ?? null),
            Cast::text($row['MainPhone'] ?? null),
            Cast::text($row['SecondaryPhone'] ?? null),
            Cast::text($row['MobilePhone'] ?? null),
            Cast::text($row['TwitterId'] ?? null),
            Cast::text($row['Manager'] ?? null),
            Cast::bool($row['IsEmployee'] ?? null),
            Cast::bool($row['IsActive'] ?? null),
            Cast::tags($row['Tags'] ?? null),
            $row,
        );
    }
}

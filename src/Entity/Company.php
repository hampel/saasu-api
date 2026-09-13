<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * An organisation that contacts belong to.
 *
 * A COMPANY IS NOT A CUSTOMER. In Saasu the customer, supplier or partner is the Contact; a
 * company is what a contact's `CompanyId` points at, and invoices are addressed to contacts.
 *
 * `LogoUrl` is marked by Saasu as unsupported and due for removal.
 */
final class Company extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $abn = null,
        public readonly ?string $website = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?string $longDescription = null,
        public readonly ?string $logoUrl = null,
        public readonly ?string $tradingName = null,
        public readonly ?string $companyEmail = null,
        public readonly ?int $lastModifiedByUserId = null,
        public readonly ?\DateTimeImmutable $createdDateUtc = null,
        public readonly ?\DateTimeImmutable $lastModifiedDateUtc = null,
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
            Cast::text($row['Abn'] ?? null),
            Cast::text($row['Website'] ?? null),
            Cast::text($row['LastUpdatedId'] ?? null),
            Cast::text($row['LongDescription'] ?? null),
            Cast::text($row['LogoUrl'] ?? null),
            Cast::text($row['TradingName'] ?? null),
            Cast::text($row['CompanyEmail'] ?? null),
            Cast::int($row['LastModifiedByUserId'] ?? null),
            Cast::datetime($row['CreatedDateUtc'] ?? null),
            Cast::datetime($row['LastModifiedDateUtc'] ?? null),
            $row,
        );
    }
}

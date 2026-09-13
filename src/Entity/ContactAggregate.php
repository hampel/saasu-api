<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A contact together with its company and contact manager, read or written as one.
 *
 * WHAT IT IS FOR: creating a customer and their company in one request instead of three, which
 * on an API with a daily quota is not a nicety. A write inserts the contact and inserts OR
 * UPDATES the company and manager - so a company given here with an `Id` is changed, not just
 * linked.
 *
 * It carries fewer contact fields than Contact - no tags, no bank details, no trading terms.
 */
final class ContactAggregate extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?string $salutation = null,
        public readonly ?string $givenName = null,
        public readonly ?string $middleInitials = null,
        public readonly ?string $familyName = null,
        public readonly ?AggregateCompany $company = null,
        public readonly ?string $positionTitle = null,
        public readonly ?string $primaryPhone = null,
        public readonly ?string $mobilePhone = null,
        public readonly ?string $homePhone = null,
        public readonly ?string $fax = null,
        public readonly ?string $emailAddress = null,
        public readonly ?string $contactReference = null,
        public readonly ?ContactManager $contactManager = null,
        public readonly ?bool $isPartner = null,
        public readonly ?bool $isCustomer = null,
        public readonly ?bool $isSupplier = null,
        public readonly ?bool $isContractor = null,
        public readonly ?Address $postalAddress = null,
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
            Cast::text($row['LastUpdatedId'] ?? null),
            Cast::text($row['Salutation'] ?? null),
            Cast::text($row['GivenName'] ?? null),
            Cast::text($row['MiddleInitials'] ?? null),
            Cast::text($row['FamilyName'] ?? null),
            Cast::nested($row['Company'] ?? null, AggregateCompany::fromArray(...)),
            Cast::text($row['PositionTitle'] ?? null),
            Cast::text($row['PrimaryPhone'] ?? null),
            Cast::text($row['MobilePhone'] ?? null),
            Cast::text($row['HomePhone'] ?? null),
            Cast::text($row['Fax'] ?? null),
            Cast::text($row['EmailAddress'] ?? null),
            Cast::text($row['ContactId'] ?? null),
            Cast::nested($row['ContactManager'] ?? null, ContactManager::fromArray(...)),
            Cast::bool($row['IsPartner'] ?? null),
            Cast::bool($row['IsCustomer'] ?? null),
            Cast::bool($row['IsSupplier'] ?? null),
            Cast::bool($row['IsContractor'] ?? null),
            Cast::nested($row['PostalAddress'] ?? null, Address::fromArray(...)),
            $row,
        );
    }
}

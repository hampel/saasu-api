<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A contact, its company and its contact manager, written in one request.
 *
 * The company and manager are inserted when they carry no id and UPDATED when they do - so
 * seeding this from an aggregate read from Saasu updates all three records.
 */
final class ContactAggregateRequest extends Payload
{
    protected const WRITABLE = [
        'Salutation',
        'GivenName',
        'MiddleInitials',
        'FamilyName',
        'PositionTitle',
        'EmailAddress',
        'PrimaryPhone',
        'MobilePhone',
        'HomePhone',
        'Fax',
        'ContactId',
        'IsCustomer',
        'IsSupplier',
        'IsContractor',
        'IsPartner',
        'PostalAddress',
        'Company',
        'ContactManager',
        'LastUpdatedId',
    ];

    public static function person(string $givenName, ?string $familyName = null): self
    {
        return self::make()
            ->withGivenName(Cast::required($givenName, 'A contact needs a given name.'))
            ->withFamilyName($familyName);
    }

    public function withSalutation(?string $salutation): static
    {
        return $this->with('Salutation', $salutation);
    }

    public function withGivenName(?string $givenName): static
    {
        return $this->with('GivenName', $givenName);
    }

    public function withMiddleInitials(?string $middleInitials): static
    {
        return $this->with('MiddleInitials', $middleInitials);
    }

    public function withFamilyName(?string $familyName): static
    {
        return $this->with('FamilyName', $familyName);
    }

    public function withPositionTitle(?string $positionTitle): static
    {
        return $this->with('PositionTitle', $positionTitle);
    }

    public function withEmailAddress(?string $emailAddress): static
    {
        return $this->with('EmailAddress', $emailAddress);
    }

    public function withPrimaryPhone(?string $primaryPhone): static
    {
        return $this->with('PrimaryPhone', $primaryPhone);
    }

    public function withMobilePhone(?string $mobilePhone): static
    {
        return $this->with('MobilePhone', $mobilePhone);
    }

    public function withHomePhone(?string $homePhone): static
    {
        return $this->with('HomePhone', $homePhone);
    }

    public function withFax(?string $fax): static
    {
        return $this->with('Fax', $fax);
    }

    public function withContactReference(?string $contactReference): static
    {
        return $this->with('ContactId', $contactReference);
    }

    public function withCustomer(?bool $customer): static
    {
        return $this->with('IsCustomer', $customer);
    }

    public function withSupplier(?bool $supplier): static
    {
        return $this->with('IsSupplier', $supplier);
    }

    public function withContractor(?bool $contractor): static
    {
        return $this->with('IsContractor', $contractor);
    }

    public function withPartner(?bool $partner): static
    {
        return $this->with('IsPartner', $partner);
    }

    /**
     * @param  AddressRequest|array<string, mixed>|null  $postalAddress
     */
    public function withPostalAddress(AddressRequest|array|null $postalAddress): static
    {
        return $this->with('PostalAddress', $postalAddress);
    }

    /**
     * @param  AggregateCompanyRequest|array<string, mixed>|null  $company
     */
    public function withCompany(AggregateCompanyRequest|array|null $company): static
    {
        return $this->with('Company', $company);
    }

    /**
     * @param  ContactManagerRequest|array<string, mixed>|null  $contactManager
     */
    public function withContactManager(ContactManagerRequest|array|null $contactManager): static
    {
        return $this->with('ContactManager', $contactManager);
    }

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}

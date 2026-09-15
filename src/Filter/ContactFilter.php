<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

use Hampel\Saasu\Api\Enum\TagSelection;

/**
 * Filters for `Contacts`.
 *
 *     ContactFilter::all()->customers()->active()->withEmail('joe@example.com')
 *
 * reference() filters on Saasu's `ContactId` parameter - your own reference for the contact, not
 * its id. See Entity\Contact.
 */
final class ContactFilter extends Filter
{
    /**
     * Records last modified in this range, in UTC - the filter Saasu recommends for a sync.
     */
    public function modifiedBetween(DateRange $range): static
    {
        return $this->withRange('LastModifiedFromDate', 'LastModifiedToDate', $range);
    }

    /**
     * Records carrying tags, combined as the selection says. An empty list removes the filter.
     *
     * @param  list<string>  $tags
     */
    public function tagged(array $tags, TagSelection $selection = TagSelection::RequireAny): static
    {
        return $this->withTagFilter($tags, $selection);
    }

    public function active(bool $active = true): static
    {
        return $this->withParameter('IsActive', $active);
    }

    public function customers(bool $customers = true): static
    {
        return $this->withParameter('IsCustomer', $customers);
    }

    public function suppliers(bool $suppliers = true): static
    {
        return $this->withParameter('IsSupplier', $suppliers);
    }

    public function contractors(bool $contractors = true): static
    {
        return $this->withParameter('IsContractor', $contractors);
    }

    public function partners(bool $partners = true): static
    {
        return $this->withParameter('IsPartner', $partners);
    }

    public function givenName(string $name): static
    {
        return $this->withText('GivenName', $name);
    }

    public function familyName(string $name): static
    {
        return $this->withText('FamilyName', $name);
    }

    public function companyName(string $name): static
    {
        return $this->withText('CompanyName', $name);
    }

    public function company(int $companyId): static
    {
        return $this->withParameter('CompanyId', $companyId);
    }

    /**
     * Contacts whose email address is exactly this.
     */
    public function withEmail(string $email): static
    {
        return $this->withText('Email', $email);
    }

    /**
     * Contacts with this reference in Saasu's `ContactId` field - the whole value, ignoring case.
     */
    public function reference(string $reference): static
    {
        return $this->withText('ContactId', $reference);
    }
}

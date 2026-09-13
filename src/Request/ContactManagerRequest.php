<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

/**
 * The contact manager inside a contact aggregate. With an `Id`, the aggregate write UPDATES that
 * contact; without one, it inserts one.
 */
final class ContactManagerRequest extends Payload
{
    protected const WRITABLE = [
        'Id',
        'Salutation',
        'GivenName',
        'MiddleInitials',
        'FamilyName',
        'PositionTitle',
        'LastUpdatedId',
    ];

    public function withId(?int $id): static
    {
        return $this->with('Id', $id);
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

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}

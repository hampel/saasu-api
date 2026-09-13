<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

/**
 * Changes to the current user. Saasu requires `FirstName`. The username and password are not
 * changed through this.
 */
final class UserRequest extends Payload
{
    protected const WRITABLE = [
        'FirstName',
        'LastName',
        'MobileNumber',
        'TimezoneId',
    ];

    public function withFirstName(?string $firstName): static
    {
        return $this->with('FirstName', $firstName);
    }

    public function withLastName(?string $lastName): static
    {
        return $this->with('LastName', $lastName);
    }

    public function withMobileNumber(?string $mobileNumber): static
    {
        return $this->with('MobileNumber', $mobileNumber);
    }

    public function withTimezoneId(?int $timezoneId): static
    {
        return $this->with('TimezoneId', $timezoneId);
    }
}

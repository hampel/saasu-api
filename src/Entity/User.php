<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * The login the credential belongs to.
 *
 * `Username` is the email address used to log in, and is not changed through a user update.
 */
final class User extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $username = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $mobileNumber = null,
        public readonly ?bool $twoFactorEnabled = null,
        public readonly ?int $timezoneId = null,
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
            Cast::text($row['Username'] ?? null),
            Cast::text($row['FirstName'] ?? null),
            Cast::text($row['LastName'] ?? null),
            Cast::text($row['MobileNumber'] ?? null),
            Cast::bool($row['Is2FAEnabled'] ?? null),
            Cast::int($row['TimezoneId'] ?? null),
            $row,
        );
    }
}

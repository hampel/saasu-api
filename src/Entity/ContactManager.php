<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * The contact manager inside a ContactAggregate - the account manager or salesperson, who is
 * themselves a contact.
 */
final class ContactManager extends Entity
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
        public readonly ?string $positionTitle = null,
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
            Cast::text($row['PositionTitle'] ?? null),
            $row,
        );
    }
}

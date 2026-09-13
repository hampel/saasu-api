<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Enum\DeletedEntityType;
use Hampel\Saasu\Api\Support\Cast;

/**
 * A record that was deleted - the other half of a sync that reads by last-modified date, since a
 * deleted record is never modified again.
 */
final class DeletedEntity extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?string $entityType = null,
        public readonly ?int $entityId = null,
        public readonly ?string $deletedByUser = null,
        public readonly ?\DateTimeImmutable $deletedAt = null,
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
            Cast::text($row['EntityType'] ?? null),
            Cast::int($row['EntityId'] ?? null),
            Cast::text($row['DeletedByUser'] ?? null),
            Cast::datetime($row['Timestamp'] ?? null),
            $row,
        );
    }

    public function type(): ?DeletedEntityType
    {
        return Cast::enum(DeletedEntityType::class, $this->entityType);
    }
}

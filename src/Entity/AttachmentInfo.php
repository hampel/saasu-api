<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * What an attachment is, without its contents - what an invoice or activity lists.
 *
 * `Name` is not in Saasu's model table and is in every sample, so it is read here.
 */
final class AttachmentInfo extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?int $size = null,
        public readonly ?int $itemIdAttachedTo = null,
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
            Cast::text($row['Description'] ?? null),
            Cast::int($row['Size'] ?? null),
            Cast::int($row['ItemIdAttachedTo'] ?? null),
            $row,
        );
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A legacy line-item attribute. Not available to new files.
 */
final class ItemAttribute extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $attributeId = null,
        public readonly ?string $value = null,
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
            Cast::int($row['AttributeId'] ?? null),
            Cast::text($row['Value'] ?? null),
            $row,
        );
    }
}

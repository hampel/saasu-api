<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * One entry of reference data - a country, a currency, a tax zone. The key is an integer for
 * most lists and a string for currencies.
 */
final class Lookup extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly mixed $key = null,
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
            $row['Key'] ?? null,
            Cast::text($row['Value'] ?? null),
            $row,
        );
    }

    public function key(): int|string|null
    {
        return is_int($this->key) || is_string($this->key) ? $this->key : null;
    }
}

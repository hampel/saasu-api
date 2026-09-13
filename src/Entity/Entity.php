<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Result\Links;

/**
 * The base of every record read from Saasu.
 *
 * ENTITIES ARE DATA AND HOLD NO CONNECTION. Anything that needs a request goes on an endpoint.
 *
 * EVERY ENTITY KEEPS `raw`, the record exactly as it arrived. A field Saasu adds after this
 * release is readable there without waiting for one, jsonSerialize() round-trips what Saasu
 * sent rather than what this package understood, and the request builders' from() seeds an
 * update from it.
 */
abstract class Entity implements \JsonSerializable
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly array $raw = [],
    ) {
    }

    /**
     * Build the entity from a record as Saasu sent it.
     *
     * @param  array<string, mixed>  $row
     */
    abstract public static function fromArray(array $row): static;

    /**
     * The record's `_links` - where its detail, its company or its billing contact is.
     */
    public function links(): Links
    {
        return Links::from($this->raw['_links'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }
}

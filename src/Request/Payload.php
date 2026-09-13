<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Entity\Entity;
use Hampel\Saasu\Api\Support\Cast;

/**
 * The body of an insert or update, built by name.
 *
 * A BUILDER SENDS ONLY THE FIELDS IT WAS GIVEN. It does not fill in defaults, because a default
 * written by this package is a value nobody chose - `IsActive: false` on a contact nobody meant
 * to deactivate.
 *
 * UPDATES START FROM THE RECORD. Saasu's documentation shows every PUT carrying the whole
 * record, and does not say what happens to a field a PUT leaves out. So the way to change one
 * field is to read the record, build the request from it, and change that field:
 *
 *     $contact = $saasu->contacts()->get(54353);
 *
 *     $saasu->contacts()->update(54353, ContactRequest::from($contact)->withEmailAddress('new@example.com'));
 *
 * from() copies every writable field the record arrived with - and its `LastUpdatedId`, which
 * the update needs - and leaves out the fields Saasu documents as read-only. Built that way, an
 * update is correct whichever way Saasu treats an omitted field.
 *
 * Immutable: every with*() returns a new builder.
 *
 * The protected helpers are named put*() rather than with*() for the reason Endpoint's carry an
 * `api` prefix: Saasu has a field called `Date`, and its wither is withDate().
 */
abstract class Payload implements \JsonSerializable
{
    /**
     * The fields from() copies out of a record. Anything Saasu computes or reports but does not
     * accept - totals, stock on hand, contact names on an invoice, timestamps - is not listed.
     *
     * @var list<string>
     */
    protected const WRITABLE = [];

    /** @var array<string, mixed> */
    private array $fields = [];

    final public function __construct()
    {
    }

    public static function make(): static
    {
        return new static();
    }

    /**
     * A request seeded from a record read from Saasu.
     */
    public static function from(Entity $entity): static
    {
        return static::fromArray($entity->raw);
    }

    /**
     * A request seeded from a record as an array - Saasu's own field names.
     *
     * @param  array<string, mixed>  $record
     */
    public static function fromArray(array $record): static
    {
        $payload = new static();

        foreach (static::WRITABLE as $field) {
            if (array_key_exists($field, $record)) {
                $payload->fields[$field] = $record[$field];
            }
        }

        return $payload;
    }

    /**
     * Set a field by Saasu's own name - for one added to the API after this release, or one this
     * builder has no method for.
     */
    public function with(string $field, mixed $value): static
    {
        $payload = clone $this;
        $payload->fields[$field] = $value instanceof self ? $value->toArray() : $value;

        return $payload;
    }

    /**
     * Remove a field, so it is not sent at all - which is not the same as sending null.
     */
    public function without(string $field): static
    {
        $payload = clone $this;
        unset($payload->fields[$field]);

        return $payload;
    }

    public function get(string $field): mixed
    {
        return $this->fields[$field] ?? null;
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    protected function putDate(string $field, \DateTimeInterface|string|null $date): static
    {
        return $this->with($field, $date === null ? null : Cast::dateString($date));
    }

    protected function putLocalDateTime(string $field, \DateTimeInterface|string|null $value): static
    {
        return $this->with($field, $value === null ? null : Cast::localDateTimeString($value));
    }

    /**
     * @param  list<self|array<string, mixed>>  $items
     */
    protected function putList(string $field, array $items): static
    {
        return $this->with($field, array_map(
            static fn (self|array $item): array => $item instanceof self ? $item->toArray() : $item,
            array_values($items)
        ));
    }

    /**
     * @param  list<string>  $tags
     */
    protected function putTags(array $tags): static
    {
        return $this->with('Tags', array_values(array_filter(
            array_map('trim', $tags),
            static fn (string $tag): bool => $tag !== ''
        )));
    }
}

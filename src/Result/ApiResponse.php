<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Result;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A successful response: the decoded body, the status, and the headers that came with it.
 *
 * SAASU DOES NOT WRAP A SINGLE RECORD. `GET Contact/54353` answers with the contact itself, its
 * fields at the top level - use object(). A list answers with the items under a key named for
 * the resource - `{"Contacts": [...], "_links": [...]}` - use collection(). The exceptions are
 * the payroll employee endpoints, which DO wrap: `{"Employee": {...}}`.
 *
 * `body` holds whatever the JSON was, because not every body is an object: `authorisation/ping`
 * answers with a bare `true`.
 */
final class ApiResponse implements \JsonSerializable
{
    /** @var array<mixed> */
    public readonly array $data;

    public function __construct(
        public readonly mixed $body,
        public readonly int $status,
        public readonly ResponseMeta $meta = new ResponseMeta(),
    ) {
        $this->data = is_array($body) ? $body : [];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * The body as an object - the record, for every single-record read.
     *
     * @return array<string, mixed>
     */
    public function object(?string $key = null): array
    {
        return Cast::object($key === null ? $this->data : ($this->data[$key] ?? null));
    }

    /**
     * The list under a key - `collection('Contacts')` - with anything that is not an object
     * dropped.
     *
     * @return list<array<string, mixed>>
     */
    public function collection(string $key): array
    {
        $rows = [];

        foreach (Cast::array($this->data[$key] ?? null) as $row) {
            if (is_array($row)) {
                $rows[] = Cast::object($row);
            }
        }

        return $rows;
    }

    /**
     * The response's own top-level `_links`.
     */
    public function links(): Links
    {
        return Links::from($this->data['_links'] ?? null);
    }

    /**
     * `StatusMessage`, which deletes and emails answer with - "Invoice has been emailed.".
     */
    public function statusMessage(): ?string
    {
        return Cast::text($this->data['StatusMessage'] ?? null);
    }

    public function isEmpty(): bool
    {
        return $this->body === null || $this->body === [];
    }

    public function jsonSerialize(): mixed
    {
        return $this->body;
    }
}

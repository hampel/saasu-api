<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Result;

use Hampel\Saasu\Api\Support\Cast;
use Hampel\Saasu\Api\Support\Redact;

/**
 * One hypermedia link: `{"rel": "related", "href": "...", "method": "GET", "title": "Company"}`.
 *
 * `rel` says how the target relates - `self`, `detail`, `list`, `next`, `update`, `delete`,
 * `related` - and `title` narrows a `related` link to which relation: `Company`,
 * `BillingContact`, `ShippingContact`, `Aggregate`, `HeaderAccount`.
 */
final class Link implements \JsonSerializable
{
    public function __construct(
        public readonly string $rel,
        public readonly string $href,
        public readonly string $method = 'GET',
        public readonly ?string $title = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): ?self
    {
        $rel = Cast::text($row['rel'] ?? null);
        $href = Cast::text($row['href'] ?? null);

        if ($rel === null || $href === null) {
            return null;
        }

        return new self($rel, $href, strtoupper(Cast::text($row['method'] ?? null) ?? 'GET'), Cast::text($row['title'] ?? null));
    }

    /**
     * The id at the end of the href's path - `Contact/54353?FileId=123` gives 54353 - which is
     * how a `related` link names the record without a field to say so.
     */
    public function id(): ?int
    {
        $path = (string) parse_url($this->href, PHP_URL_PATH);

        return preg_match('~/(\d+)/?$~', $path, $match) === 1 ? (int) $match[1] : null;
    }

    /**
     * @return array<string, string|null>
     */
    public function jsonSerialize(): array
    {
        return ['rel' => $this->rel, 'href' => $this->href, 'method' => $this->method, 'title' => $this->title];
    }

    /**
     * @return array<string, string|null>
     */
    public function __debugInfo(): array
    {
        return ['rel' => $this->rel, 'href' => Redact::uri($this->href), 'method' => $this->method, 'title' => $this->title];
    }
}

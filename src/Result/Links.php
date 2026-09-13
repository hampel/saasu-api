<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Result;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A resource's `_links`.
 *
 * Every Saasu response, and every item inside a list response, carries these. They are useful
 * for one thing a field cannot do - `related('Company')` says where a contact's company is -
 * and are followed through Connection::follow(), which refuses a link that does not point at
 * the configured API.
 *
 * @implements \IteratorAggregate<int, Link>
 */
final class Links implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * @param  list<Link>  $links
     */
    public function __construct(
        public readonly array $links = [],
    ) {
    }

    public static function from(mixed $value): self
    {
        $links = [];

        foreach (Cast::objects($value, Link::fromArray(...)) as $link) {
            if ($link !== null) {
                $links[] = $link;
            }
        }

        return new self($links);
    }

    public function first(string $rel): ?Link
    {
        foreach ($this->links as $link) {
            if (strcasecmp($link->rel, $rel) === 0) {
                return $link;
            }
        }

        return null;
    }

    /**
     * @return list<Link>
     */
    public function all(string $rel): array
    {
        return array_values(array_filter($this->links, static fn (Link $link): bool => strcasecmp($link->rel, $rel) === 0));
    }

    public function self(): ?Link
    {
        return $this->first('self');
    }

    public function next(): ?Link
    {
        return $this->first('next');
    }

    public function related(string $title): ?Link
    {
        foreach ($this->all('related') as $link) {
            if ($link->title !== null && strcasecmp($link->title, $title) === 0) {
                return $link;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->links);
    }

    /**
     * @return \ArrayIterator<int, Link>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->links);
    }

    /**
     * @return list<Link>
     */
    public function jsonSerialize(): array
    {
        return $this->links;
    }
}

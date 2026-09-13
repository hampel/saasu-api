<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

use Hampel\Saasu\Api\Enum\TagSelection;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;

/**
 * The query parameters for a list request, built by name rather than by string.
 *
 * Immutable: every with*() method returns a new filter, so a base filter can be shared and
 * narrowed without one narrowing leaking into another.
 *
 * A MISSPELT PARAMETER IS MOST LIKELY IGNORED, NOT REFUSED - which is why these exist. ASP.NET,
 * which Saasu runs on, drops query parameters it does not bind, so a misspelt name widens a query
 * to everything and looks exactly like a result. `vendor/bin/rig lists` probes it.
 */
abstract class Filter
{
    /** @var array<string, scalar> */
    private array $parameters = [];

    public static function all(): static
    {
        /** @phpstan-ignore new.static */
        return new static();
    }

    /**
     * The parameters as they will be sent.
     *
     * @return array<string, scalar>
     */
    public function toQuery(): array
    {
        return $this->parameters;
    }

    /**
     * Set a parameter this class has no method for - one added to the API after this release.
     */
    public function withParameter(string $name, string|int|float|bool|null $value): static
    {
        $filter = clone $this;

        if ($value === null) {
            unset($filter->parameters[$name]);
        } else {
            $filter->parameters[$name] = $value;
        }

        return $filter;
    }

    protected function withRange(string $fromName, string $toName, ?DateRange $range): static
    {
        $filter = $this->withParameter($fromName, null)->withParameter($toName, null);

        return $range === null
            ? $filter
            : $filter->withParameter($fromName, $range->from)->withParameter($toName, $range->to);
    }

    /**
     * @param  list<string>  $tags
     */
    protected function withTagFilter(array $tags, TagSelection $selection): static
    {
        $tags = array_values(array_filter(array_map('trim', $tags), static fn (string $tag): bool => $tag !== ''));

        foreach ($tags as $tag) {
            if (str_contains($tag, ',')) {
                throw new InvalidArgumentException(sprintf(
                    'Saasu takes tags as one comma-separated parameter, so a tag cannot contain a comma: "%s".',
                    $tag
                ));
            }
        }

        if ($tags === []) {
            return $this->withParameter('Tags', null)->withParameter('TagSelection', null);
        }

        return $this->withParameter('Tags', implode(',', $tags))->withParameter('TagSelection', $selection->value);
    }

    protected function withText(string $name, ?string $value, int $max = 0): static
    {
        if ($value !== null && $max > 0 && (int) preg_match_all('/./su', $value) > $max) {
            throw new InvalidArgumentException(sprintf('Saasu accepts at most %d characters for %s.', $max, $name));
        }

        return $this->withParameter($name, $value === null || trim($value) === '' ? null : trim($value));
    }
}

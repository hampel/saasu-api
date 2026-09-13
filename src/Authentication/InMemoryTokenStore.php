<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Authentication;

/**
 * Keeps grants for the life of this object - one process, at most. See TokenStore for why that
 * is the wrong store for a web application.
 */
final class InMemoryTokenStore implements TokenStore
{
    /** @var array<string, AccessGrant> */
    private array $grants = [];

    public function get(string $key): ?AccessGrant
    {
        return $this->grants[$key] ?? null;
    }

    public function put(string $key, AccessGrant $grant): void
    {
        $this->grants[$key] = $grant;
    }

    public function forget(string $key): void
    {
        unset($this->grants[$key]);
    }
}

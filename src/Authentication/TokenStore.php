<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Authentication;

/**
 * Where OAuth keeps the grant it obtained, so the next process does not have to ask again.
 *
 * THIS IS NOT A PERFORMANCE DETAIL. Every token request is a request against Saasu's daily
 * quota, and a PHP application is many short processes. With the default in-memory store, a
 * web application authenticates on every page view that touches Saasu and a queue worker on
 * every restart - a password grant per request, spent from the same budget as the work, from
 * an API that blocks the whole file for 24 hours once that budget is gone. Anything longer
 * lived than one process wants a store backed by something shared: a cache, a database row.
 *
 * Implementations should treat the grant as a credential - it holds a refresh token valid for
 * twelve months. AccessGrant::toArray() and fromArray() are the portable form, for a store that
 * cannot hold objects.
 */
interface TokenStore
{
    public function get(string $key): ?AccessGrant;

    public function put(string $key, AccessGrant $grant): void;

    public function forget(string $key): void;
}

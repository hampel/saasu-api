<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Support;

/**
 * How many requests a client and everything derived from it have sent.
 *
 * Shared by reference between a Client and the clients its with*() methods return, because
 * the thing being counted - Saasu's daily quota - belongs to the file and the login, not to one
 * PHP object. A budget that reset whenever withFileId() was called would guard nothing.
 */
final class RequestCounter
{
    private int $count = 0;

    public function increment(): int
    {
        return ++$this->count;
    }

    public function count(): int
    {
        return $this->count;
    }
}

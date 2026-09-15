<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Throttle;

/**
 * Called before every request is sent, to keep the client inside Saasu's rate limit.
 *
 * SAASU ALLOWS ONE REQUEST A SECOND, per its published API limits. The default implementation,
 * IntervalThrottle, enforces that within one PHP process. It cannot see another process: two
 * queue workers each keeping to one a second are two a second between them. An application
 * running several workers against one file wants an implementation backed by something they
 * share - a cache lock, a Redis rate limiter - and this interface is where that goes.
 *
 * Token requests pass through the throttle too, because Saasu counts them.
 *
 * THE FILE ID IS PASSED because Saasu's limits are a file's, and one client can reach several -
 * `Client::withFileId()` derives a client for another file that shares this throttle. An
 * implementation keyed by file waits on the file the request is for, not the one the client was
 * built with.
 */
interface Throttle
{
    /**
     * Block until a request may be sent.
     *
     * @param  int|null  $fileId  the file the request names; null for one that names none - a
     *                            token request, or the list of files a login reaches
     */
    public function wait(?int $fileId): void;
}

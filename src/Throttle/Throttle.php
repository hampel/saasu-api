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
 */
interface Throttle
{
    /**
     * Block until a request may be sent.
     */
    public function wait(): void;
}

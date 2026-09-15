<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Throttle;

/**
 * Sends immediately. For a test suite driving a stub transport, and for an application whose
 * HTTP client already rate-limits - which would otherwise wait twice.
 */
final class NoThrottle implements Throttle
{
    public function wait(?int $fileId): void
    {
    }
}

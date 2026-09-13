<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Authentication;

use Hampel\Saasu\Api\Connection;
use Psr\Http\Message\RequestInterface;

/**
 * No credential - for the requests that obtain one, and for a password reset.
 */
final class Anonymous implements Authentication
{
    public function authenticate(RequestInterface $request, Connection $connection): RequestInterface
    {
        return $request;
    }

    public function recover(Connection $connection): bool
    {
        return false;
    }

    public function describe(): string
    {
        return 'no credential';
    }
}

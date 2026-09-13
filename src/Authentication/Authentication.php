<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Authentication;

use Hampel\Saasu\Api\Connection;
use Psr\Http\Message\RequestInterface;

/**
 * How a request proves who it is.
 *
 * Saasu accepts two schemes, and they put the credential in different places:
 *
 *  - OAUTH 2, the one Saasu prefers: a bearer token in the `Authorization` header, obtained
 *    from a username and password and refreshed as it expires. See OAuth and AccessToken.
 *  - A WEB SERVICES ACCESS KEY, the legacy scheme Saasu says it will retire: a key in the
 *    QUERY STRING, beside the FileId. See WsAccessKey.
 *
 * The connection is passed in because an OAuth credential may have to make a request of its
 * own - for a token, or a refreshed one - before it can authenticate this one, and it must do
 * so through the same transport, throttle and request budget as everything else.
 */
interface Authentication
{
    public function authenticate(RequestInterface $request, Connection $connection): RequestInterface;

    /**
     * Saasu answered a request with 401. Answer whether sending it again might now succeed.
     *
     * An OAuth credential answers true after discarding the token it used, since a token can be
     * revoked before it expires. A static credential answers false: the same key will be
     * refused the same way. Connection retries at most once, so a true here cannot loop.
     */
    public function recover(Connection $connection): bool;

    /**
     * What this credential is, for a log line or an exception message.
     *
     * MUST NOT INCLUDE A SECRET, or any part of one long enough to be useful.
     */
    public function describe(): string;
}

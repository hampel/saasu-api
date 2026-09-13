<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Authentication;

use Hampel\Saasu\Api\Connection;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

/**
 * A web services access key, from Saasu's Settings - Web Services page.
 *
 * THE KEY TRAVELS IN THE URL. It is a `wsAccessKey` query parameter, so it is in every URI this
 * client builds - and URIs are what go into log lines, exception messages and a proxy's access
 * log. Connection::redact() takes it out of everything this package writes; what an HTTP
 * client, a proxy or a web server between here and Saasu writes is outside its reach. That is
 * the main reason to prefer OAuth where there is a choice.
 *
 * LEGACY. Saasu describes this scheme as supported but to be "phased out sometime in the
 * future". A key belongs to one user and one file, so a client using it should be configured
 * with that file's id.
 */
final class WsAccessKey implements Authentication
{
    public const PARAMETER = 'wsAccessKey';

    private readonly string $key;

    public function __construct(#[\SensitiveParameter] string $key)
    {
        $key = trim($key);

        if ($key === '') {
            throw new InvalidArgumentException('A Saasu web services access key is required.');
        }

        $this->key = $key;
    }

    /**
     * Adds the key to the query string, replacing one already there - a `_links` href can carry
     * the key it was issued with, and sending two is sending an ambiguity.
     */
    public function authenticate(RequestInterface $request, Connection $connection): RequestInterface
    {
        $uri = $request->getUri();

        $pairs = array_filter(
            explode('&', $uri->getQuery()),
            static fn (string $pair): bool => $pair !== ''
                && strcasecmp(rawurldecode(explode('=', $pair, 2)[0]), self::PARAMETER) !== 0
        );

        $pairs[] = self::PARAMETER . '=' . rawurlencode($this->key);

        return $request->withUri($uri->withQuery(implode('&', $pairs)));
    }

    public function recover(Connection $connection): bool
    {
        return false;
    }

    public function describe(): string
    {
        $length = strlen($this->key);

        return $length > 12
            ? sprintf('a Saasu web services access key ending %s (%d characters)', substr($this->key, -4), $length)
            : sprintf('a Saasu web services access key of %d characters', $length);
    }

    public function __toString(): string
    {
        return $this->describe();
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['key' => $this->describe()];
    }
}

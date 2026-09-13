<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Authentication;

use Hampel\Saasu\Api\Connection;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

/**
 * An OAuth access token the caller already holds, sent as a bearer credential and never renewed.
 *
 * For an application that manages Saasu's tokens itself. It EXPIRES - Saasu's documented sample
 * grant has an `expires_in` of 10800, three hours - and nothing here will notice until Saasu
 * answers 401. OAuth is the credential that renews itself.
 */
final class AccessToken implements Authentication
{
    private readonly string $token;

    public function __construct(#[\SensitiveParameter] string $token)
    {
        $token = trim($token);

        if ($token === '') {
            throw new InvalidArgumentException('A Saasu access token is required.');
        }

        $this->token = $token;
    }

    public function authenticate(RequestInterface $request, Connection $connection): RequestInterface
    {
        return $request->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function recover(Connection $connection): bool
    {
        return false;
    }

    public function describe(): string
    {
        return sprintf('a Saasu access token of %d characters', strlen($this->token));
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
        return ['token' => $this->describe()];
    }
}

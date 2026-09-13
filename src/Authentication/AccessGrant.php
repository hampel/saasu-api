<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Authentication;

use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Support\Cast;

/**
 * What Saasu's token endpoints answer with: an access token, a refresh token, and when the
 * first stops working.
 *
 *     {"access_token": "eyJ0...", "token_type": "Bearer", "expires_in": 10800,
 *      "refresh_token": "eyJ0...", "scope": "full fileid:15431 fileid:14078"}
 *
 * THE SCOPE LISTS THE FILES THE USER CAN REACH. Saasu accepts no scope on the way in, but writes
 * one `fileid:NNN` per accessible file on the way out - fileIds() reads them, which is the
 * cheapest answer to "which file ids may I configure" there is.
 *
 * The expiry is stored as an instant rather than as `expires_in`, because a number of seconds
 * means nothing once the grant has sat in a cache for an hour.
 */
final class AccessGrant implements \JsonSerializable
{
    /**
     * How long before the recorded expiry a token is treated as expired, so a request is not
     * sent with a token that dies in transit.
     */
    public const EXPIRY_LEEWAY = 60;

    public function __construct(
        #[\SensitiveParameter] public readonly string $accessToken,
        public readonly \DateTimeImmutable $expiresAt,
        #[\SensitiveParameter] public readonly ?string $refreshToken = null,
        public readonly string $tokenType = 'Bearer',
        public readonly string $scope = '',
    ) {
        if (trim($accessToken) === '') {
            throw new InvalidArgumentException('An access grant needs an access token.');
        }
    }

    /**
     * @param  array<string, mixed>  $body  the token endpoint's decoded response
     */
    public static function fromResponse(array $body, \DateTimeImmutable $issuedAt): self
    {
        $token = Cast::text($body['access_token'] ?? null);

        if ($token === null) {
            throw new InvalidArgumentException('Saasu answered the token request without an access_token.');
        }

        $expiresIn = max(0, Cast::int($body['expires_in'] ?? null) ?? 0);

        return new self(
            $token,
            $issuedAt->modify(sprintf('+%d seconds', $expiresIn)),
            Cast::text($body['refresh_token'] ?? null),
            Cast::text($body['token_type'] ?? null) ?? 'Bearer',
            Cast::string($body['scope'] ?? null) ?? '',
        );
    }

    /**
     * The portable form written by toArray(), for a store that holds arrays or JSON.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $expiresAt = Cast::datetime($data['expires_at'] ?? null);
        $token = Cast::text($data['access_token'] ?? null);

        if ($token === null || $expiresAt === null) {
            throw new InvalidArgumentException('A stored access grant needs access_token and expires_at.');
        }

        return new self(
            $token,
            $expiresAt,
            Cast::text($data['refresh_token'] ?? null),
            Cast::text($data['token_type'] ?? null) ?? 'Bearer',
            Cast::string($data['scope'] ?? null) ?? '',
        );
    }

    public function isExpired(\DateTimeImmutable $now, int $leeway = self::EXPIRY_LEEWAY): bool
    {
        return $now->modify(sprintf('+%d seconds', $leeway)) >= $this->expiresAt;
    }

    public function canRefresh(): bool
    {
        return $this->refreshToken !== null;
    }

    /**
     * The same grant marked as expired, keeping its refresh token - what OAuth stores when Saasu
     * refuses a token that had not yet reached its expiry.
     */
    public function expire(): self
    {
        return new self($this->accessToken, new \DateTimeImmutable('@0'), $this->refreshToken, $this->tokenType, $this->scope);
    }

    /**
     * The same grant with a refresh token filled in, when the response that produced it did not
     * carry one. Saasu's documentation shows a refresh answering with a new refresh token, but
     * nothing promises it, and dropping the old one would strand a credential that still works.
     */
    public function withRefreshTokenFallback(?string $refreshToken): self
    {
        return $this->refreshToken !== null || $refreshToken === null
            ? $this
            : new self($this->accessToken, $this->expiresAt, $refreshToken, $this->tokenType, $this->scope);
    }

    /**
     * The file ids the scope grants access to.
     *
     * @return list<int>
     */
    public function fileIds(): array
    {
        $ids = [];

        foreach (preg_split('/\s+/', trim($this->scope)) ?: [] as $part) {
            if (preg_match('/^fileid:(\d+)$/i', $part, $match) === 1) {
                $ids[] = (int) $match[1];
            }
        }

        return $ids;
    }

    public function grantsFile(int $fileId): bool
    {
        return in_array($fileId, $this->fileIds(), true);
    }

    /**
     * THE PORTABLE FORM, AND IT CONTAINS BOTH TOKENS. This is what a TokenStore persists, so it
     * is a credential: the refresh token alone can mint access tokens for twelve months.
     *
     * @return array{access_token: string, refresh_token: string|null, token_type: string, scope: string, expires_at: string}
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'token_type' => $this->tokenType,
            'scope' => $this->scope,
            'expires_at' => $this->expiresAt->format(DATE_ATOM),
        ];
    }

    /**
     * Without the tokens - so a grant passed to a logger or json_encode() by mistake does not
     * leak them. toArray() is the deliberate form.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'accessToken' => sprintf('(%d characters)', strlen($this->accessToken)),
            'refreshToken' => $this->refreshToken === null ? null : sprintf('(%d characters)', strlen($this->refreshToken)),
            'tokenType' => $this->tokenType,
            'scope' => $this->scope,
            'expiresAt' => $this->expiresAt->format(DATE_ATOM),
        ];
    }
}

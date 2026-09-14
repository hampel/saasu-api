<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Authentication\AccessGrant;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Exception\NotAuthenticatedException;

/**
 * Obtaining, refreshing and testing OAuth tokens, at `authorisation/*`.
 *
 * Most applications never call this directly - Authentication\OAuth does it for them. It is here
 * for the one-off jobs: seeding a grant interactively for a user with two-factor authentication,
 * or checking a token another system handed over.
 *
 * EVERY CALL HERE IS A REQUEST AGAINST THE FILE'S DAILY QUOTA, a token request included.
 *
 * Token requests are sent without a credential or a file id, as JSON POSTs. Saasu also offers the
 * password grant as a GET with the password in the query string; that form is not wrapped,
 * because a password in a URL is a password in every access log between here and Saasu.
 */
final class Authorisation extends Endpoint
{
    /**
     * Exchange a username and password for a grant. Fails for a user with 2FA - see
     * tokenWithTwoFactor().
     *
     * Raises NotAuthenticatedException with an oauthError() of `invalid_credentials` for a bad
     * username or password - measured.
     */
    public function token(string $username, #[\SensitiveParameter] string $password, ?\DateTimeImmutable $now = null): AccessGrant
    {
        return $this->grant('authorisation/token', $this->passwordPayload($username, $password), $now);
    }

    /**
     * The password grant for a user who may have two-factor authentication on.
     *
     * CALLED WITHOUT A CODE, THIS TEXTS ONE TO THE USER'S MOBILE and raises
     * TwoFactorRequiredException. Called again with that code, it answers with the grant. For a
     * user without 2FA it answers with the grant the first time.
     */
    public function tokenWithTwoFactor(
        string $username,
        #[\SensitiveParameter] string $password,
        #[\SensitiveParameter] ?string $verificationCode = null,
        ?\DateTimeImmutable $now = null,
    ): AccessGrant {
        $payload = $this->passwordPayload($username, $password);

        if ($verificationCode !== null && trim($verificationCode) !== '') {
            $payload['verification_code'] = trim($verificationCode);
        }

        return $this->grant('authorisation/token-2fa', $payload, $now);
    }

    /**
     * Exchange a refresh token for a new grant. Raises NotAuthenticatedException with an
     * oauthError() of `invalid_client` for a refresh token Saasu does not accept - measured.
     */
    public function refresh(#[\SensitiveParameter] string $refreshToken, ?\DateTimeImmutable $now = null): AccessGrant
    {
        if (trim($refreshToken) === '') {
            throw new InvalidArgumentException('A refresh token is required.');
        }

        return $this->grant('authorisation/refresh', ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken], $now);
    }

    /**
     * Whether the client's credential is accepted - true, or false for a refused credential.
     *
     * FILE-SCOPED. Measured 2026-09-14: without a file id Saasu answers 401
     * `{"Message": "No FileId or Invalid FileId supplied."}`, which read as "the token is not
     * valid" would be the wrong answer to the question this asks.
     */
    public function ping(): bool
    {
        try {
            return $this->connection->get('authorisation/ping')->body === true;
        } catch (NotAuthenticatedException) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private function passwordPayload(string $username, #[\SensitiveParameter] string $password): array
    {
        if (trim($username) === '' || $password === '') {
            throw new InvalidArgumentException('A Saasu token request needs both a username and a password.');
        }

        // `scope` is sent as Saasu's own examples send it. Saasu says it accepts no scope yet.
        return ['grant_type' => 'password', 'username' => trim($username), 'password' => $password, 'scope' => 'full'];
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function grant(string $path, array $payload, ?\DateTimeImmutable $now): AccessGrant
    {
        $issuedAt = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return AccessGrant::fromResponse($this->connection->unauthenticated()->post($path, $payload)->object(), $issuedAt);
    }
}

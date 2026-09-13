<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Authentication;

use Hampel\Saasu\Api\Connection;
use Hampel\Saasu\Api\Endpoint\Authorisation;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Exception\NotAuthenticatedException;
use Psr\Http\Message\RequestInterface;

/**
 * Saasu's preferred credential: a bearer token that renews itself.
 *
 *     OAuth::password('api@example.com', $password, $store)
 *
 * Saasu supports one OAuth grant, the password grant. The token it issues expires - three hours
 * in Saasu's documented sample - and comes with a refresh token that lasts twelve months. This
 * class obtains a token on the first request, reuses it while it is valid, refreshes it when it
 * is not, and falls back to the password when a refresh is refused.
 *
 * THE STORE IS THE PART TO GET RIGHT - see TokenStore. Every token request counts against the
 * file's daily quota.
 *
 * TWO-FACTOR AUTHENTICATION CANNOT BE ANSWERED UNATTENDED. For a user with 2FA on, obtain a
 * grant once through Endpoint\Authorisation::tokenWithTwoFactor() and seed with it:
 *
 *     OAuth::grant($grant, $store)
 *
 * which refreshes for as long as the refresh token lasts and raises when it no longer can.
 *
 * CONCURRENT REFRESHES ARE NOT COORDINATED HERE. Two processes that find the same token expired
 * both refresh it; if Saasu rotates refresh tokens, the loser's is refused and it falls back to
 * the password (or raises, seeded from a grant). A store with a lock is where that belongs.
 */
final class OAuth implements Authentication
{
    /** @var \Closure(): \DateTimeImmutable */
    private readonly \Closure $clock;

    /**
     * Whether the seed grant has been superseded - stored over, or refused. Once it has, it is
     * never consulted again: a refresh token Saasu has refused would otherwise be retried on
     * every request, each attempt spending quota on a known answer.
     */
    private bool $seedSpent = false;

    /**
     * @param  (callable(): \DateTimeImmutable)|null  $clock
     */
    private function __construct(
        private readonly ?string $username,
        #[\SensitiveParameter] private readonly ?string $password,
        private readonly ?AccessGrant $seed,
        private readonly TokenStore $store,
        private readonly string $key,
        ?callable $clock = null,
    ) {
        $this->clock = $clock !== null
            ? \Closure::fromCallable($clock)
            : static fn (): \DateTimeImmutable => new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Authenticate with a username and password, refreshing as needed.
     *
     * Use a user WITHOUT two-factor authentication - Saasu cannot issue it a token otherwise -
     * and ideally one created for the integration, so its access can be narrowed and revoked
     * without locking a person out.
     *
     * @param  (callable(): \DateTimeImmutable)|null  $clock
     */
    public static function password(
        string $username,
        #[\SensitiveParameter] string $password,
        ?TokenStore $store = null,
        ?callable $clock = null,
    ): self {
        $username = trim($username);

        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('Saasu OAuth needs both a username and a password.');
        }

        return new self($username, $password, null, $store ?? new InMemoryTokenStore(), self::keyFor($username), $clock);
    }

    /**
     * Authenticate from a grant obtained elsewhere, refreshing it for as long as Saasu allows.
     *
     * The key names the grant in the store; give each Saasu user its own.
     *
     * @param  (callable(): \DateTimeImmutable)|null  $clock
     */
    public static function grant(
        AccessGrant $grant,
        ?TokenStore $store = null,
        string $key = 'saasu-oauth:default',
        ?callable $clock = null,
    ): self {
        return new self(null, null, $grant, $store ?? new InMemoryTokenStore(), $key, $clock);
    }

    /**
     * The store key for a username - hashed, so a cache listing does not double as a list of
     * the logins that have Saasu access.
     */
    public static function keyFor(string $username): string
    {
        return 'saasu-oauth:' . hash('sha256', strtolower(trim($username)));
    }

    public function key(): string
    {
        return $this->key;
    }

    public function authenticate(RequestInterface $request, Connection $connection): RequestInterface
    {
        $grant = $this->currentGrant($connection);

        // Always "Bearer": Saasu's own authentication page shows a grant with a token_type of
        // "example", and echoing whatever arrived would send that.
        return $request->withHeader('Authorization', 'Bearer ' . $grant->accessToken);
    }

    /**
     * The grant in use, obtaining or refreshing one if necessary. Public for an application
     * that wants the token or the scope's file ids without making a data request.
     */
    public function currentGrant(Connection $connection): AccessGrant
    {
        $current = $this->store->get($this->key) ?? ($this->seedSpent ? null : $this->seed);
        $now = ($this->clock)();

        if ($current !== null && !$current->isExpired($now)) {
            return $current;
        }

        $authorisation = new Authorisation($connection);

        if ($current?->refreshToken !== null) {
            try {
                return $this->remember(
                    $authorisation->refresh($current->refreshToken, $now)->withRefreshTokenFallback($current->refreshToken)
                );
            } catch (NotAuthenticatedException $e) {
                $this->store->forget($this->key);
                $this->seedSpent = true;

                if ($this->username === null || $this->password === null) {
                    throw $e;
                }
            }
        }

        if ($this->username === null || $this->password === null) {
            throw new NotAuthenticatedException(
                'The Saasu access grant has expired and has no refresh token, and this credential was '
                    . 'not given a password to obtain another.',
                401
            );
        }

        return $this->remember($authorisation->token($this->username, $this->password, $now));
    }

    public function recover(Connection $connection): bool
    {
        $grant = $this->store->get($this->key) ?? ($this->seedSpent ? null : $this->seed);

        if ($grant === null) {
            return $this->password !== null;
        }

        $this->store->put($this->key, $grant->expire());
        $this->seedSpent = true;

        return $grant->canRefresh() || $this->password !== null;
    }

    /**
     * Forget the stored grant, so the next request authenticates from scratch.
     */
    public function forget(): void
    {
        $this->store->forget($this->key);
    }

    public function describe(): string
    {
        if ($this->username === null) {
            return 'Saasu OAuth from a stored grant';
        }

        $at = strrpos($this->username, '@');

        return sprintf(
            'Saasu OAuth as %s',
            $at === false ? substr($this->username, 0, 1) . '***' : substr($this->username, 0, 1) . '***' . substr($this->username, $at)
        );
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
        return ['credential' => $this->describe(), 'key' => $this->key];
    }

    private function remember(AccessGrant $grant): AccessGrant
    {
        $this->store->put($this->key, $grant);
        $this->seedSpent = true;

        return $grant;
    }
}

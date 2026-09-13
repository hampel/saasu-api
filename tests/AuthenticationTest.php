<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Tests;

use Hampel\Saasu\Api\Authentication\AccessGrant;
use Hampel\Saasu\Api\Authentication\AccessToken;
use Hampel\Saasu\Api\Authentication\InMemoryTokenStore;
use Hampel\Saasu\Api\Authentication\OAuth;
use Hampel\Saasu\Api\Authentication\WsAccessKey;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Exception\NotAuthenticatedException;
use Hampel\Saasu\Api\Support\Cast;

final class AuthenticationTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = new \DateTimeImmutable('2026-09-13 10:00:00', new \DateTimeZone('UTC'));
    }

    private function clock(): \Closure
    {
        return fn (): \DateTimeImmutable => $this->now;
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenBody(string $access = 'access-1', string $refresh = 'refresh-1', int $expiresIn = 10800): array
    {
        return [
            'access_token' => $access,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'refresh_token' => $refresh,
            'scope' => 'full fileid:123 fileid:456',
        ];
    }

    public function testTheAccessKeyIsAddedToTheQueryString(): void
    {
        $this->pushFixture('contacts');

        $this->saasu(authentication: new WsAccessKey('ABCDEF0123456789ABCDEF'))->contacts()->list();

        $query = $this->sentQuery();

        $this->assertSame('ABCDEF0123456789ABCDEF', $query['wsAccessKey']);
        $this->assertSame('123', $query['FileId']);
        $this->assertFalse($this->client->lastRequest()->hasHeader('Authorization'));
    }

    /**
     * Saasu writes the key back into `_links` hrefs; following one must not send two keys.
     */
    public function testAnAccessKeyAlreadyInAFollowedLinkIsReplacedNotDoubled(): void
    {
        $this->pushFixture('file-identity');

        $this->saasu(authentication: new WsAccessKey('ABCDEF0123456789ABCDEF'))
            ->connection()
            ->follow('https://api.saasu.com/FileIdentity?wsaccesskey=0000EXAMPLEKEY0000&fileid=1');

        $query = $this->client->lastRequest()->getUri()->getQuery();

        $this->assertSame(1, substr_count(strtolower($query), 'wsaccesskey='));
        $this->assertStringContainsString('wsAccessKey=ABCDEF0123456789ABCDEF', $query);
        $this->assertStringNotContainsString('0000EXAMPLEKEY0000', $query);
    }

    public function testCredentialsDoNotPrint(): void
    {
        $key = new WsAccessKey('ABCDEF0123456789ABCDEF');
        $token = new AccessToken('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9');
        $oauth = OAuth::password('api@example.com', 'hunter2-long-password');

        foreach ([$key, $token, $oauth] as $credential) {
            $printed = print_r($credential, true) . (string) $credential;

            $this->assertStringNotContainsString('ABCDEF0123456789', $printed);
            $this->assertStringNotContainsString('eyJ0eXAi', $printed);
            $this->assertStringNotContainsString('hunter2', $printed);
        }

        $this->assertSame('Saasu OAuth as a***@example.com', $oauth->describe());
    }

    public function testOAuthObtainsATokenOnceAndReusesIt(): void
    {
        $this->client->pushJson(200, $this->tokenBody());
        $this->pushFixture('contact');
        $this->pushFixture('contact');

        $saasu = $this->saasu(authentication: OAuth::password('api@example.com', 'secret', clock: $this->clock()));
        $saasu->contacts()->get(1);
        $saasu->contacts()->get(2);

        $this->assertCount(3, $this->client->requests);

        $tokenRequest = $this->client->requests[0];
        $this->assertSame('/authorisation/token', $tokenRequest->getUri()->getPath());
        $this->assertSame('', $tokenRequest->getUri()->getQuery(), 'a token request carries no file id');
        $this->assertFalse($tokenRequest->hasHeader('Authorization'));
        $this->assertSame(
            ['grant_type' => 'password', 'username' => 'api@example.com', 'password' => 'secret', 'scope' => 'full'],
            json_decode((string) $tokenRequest->getBody(), true)
        );

        $this->assertSame('Bearer access-1', $this->client->requests[1]->getHeaderLine('Authorization'));
        $this->assertSame('Bearer access-1', $this->client->requests[2]->getHeaderLine('Authorization'));
    }

    /**
     * The token request is counted and throttled like any other, because Saasu counts it.
     */
    public function testTheTokenRequestCountsAgainstTheBudget(): void
    {
        $this->client->pushJson(200, $this->tokenBody());
        $this->pushFixture('contact');

        $saasu = $this->saasu(authentication: OAuth::password('api@example.com', 'secret', clock: $this->clock()));
        $saasu->contacts()->get(1);

        $this->assertSame(2, $saasu->requestsSent());
    }

    public function testAStoredGrantIsUsedWithoutAskingForAnother(): void
    {
        $store = new InMemoryTokenStore();
        $store->put(OAuth::keyFor('API@example.com '), AccessGrant::fromResponse($this->tokenBody('cached'), $this->now));
        $this->pushFixture('contact');

        $this->saasu(authentication: OAuth::password('api@example.com', 'secret', $store, $this->clock()))->contacts()->get(1);

        $this->assertCount(1, $this->client->requests);
        $this->assertSame('Bearer cached', $this->client->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testAnExpiredGrantIsRefreshed(): void
    {
        $store = new InMemoryTokenStore();
        $oauth = OAuth::password('api@example.com', 'secret', $store, $this->clock());
        $store->put($oauth->key(), AccessGrant::fromResponse($this->tokenBody('old', 'refresh-old'), $this->now->modify('-4 hours')));

        $this->client->pushJson(200, $this->tokenBody('new', 'refresh-new'));
        $this->pushFixture('contact');

        $this->saasu(authentication: $oauth)->contacts()->get(1);

        $this->assertSame('/authorisation/refresh', $this->client->requests[0]->getUri()->getPath());
        $this->assertSame(
            ['grant_type' => 'refresh_token', 'refresh_token' => 'refresh-old'],
            json_decode((string) $this->client->requests[0]->getBody(), true)
        );
        $this->assertSame('Bearer new', $this->client->lastRequest()->getHeaderLine('Authorization'));
        $this->assertSame('refresh-new', $store->get($oauth->key())?->refreshToken);
    }

    /**
     * Treated as expired a minute early, so a token does not die between being checked and
     * being used.
     */
    public function testAGrantAboutToExpireIsTreatedAsExpired(): void
    {
        $grant = AccessGrant::fromResponse($this->tokenBody(expiresIn: 30), $this->now);

        $this->assertTrue($grant->isExpired($this->now));
        $this->assertFalse(AccessGrant::fromResponse($this->tokenBody(expiresIn: 120), $this->now)->isExpired($this->now));
    }

    public function testARefusedRefreshFallsBackToThePassword(): void
    {
        $store = new InMemoryTokenStore();
        $oauth = OAuth::password('api@example.com', 'secret', $store, $this->clock());
        $store->put($oauth->key(), AccessGrant::fromResponse($this->tokenBody('old', 'refresh-old'), $this->now->modify('-4 hours')));

        $this->client->pushJson(401, ['error' => 'invalid_client', 'error_description' => 'Not a valid refresh token', 'data' => null, 'is_2fa_code_required' => false, 'is_2fa_code_sent' => false]);
        $this->client->pushJson(200, $this->tokenBody('fresh'));
        $this->pushFixture('contact');

        $this->saasu(authentication: $oauth)->contacts()->get(1);

        $this->assertSame('/authorisation/refresh', $this->client->requests[0]->getUri()->getPath());
        $this->assertSame('/authorisation/token', $this->client->requests[1]->getUri()->getPath());
        $this->assertSame('Bearer fresh', $this->client->lastRequest()->getHeaderLine('Authorization'));
    }

    /**
     * A token can be revoked before it expires. One 401 discards it and retries once; a second
     * 401 is raised rather than looped on.
     */
    public function testA401DiscardsTheTokenAndRetriesOnce(): void
    {
        $store = new InMemoryTokenStore();
        $oauth = OAuth::password('api@example.com', 'secret', $store, $this->clock());
        $store->put($oauth->key(), AccessGrant::fromResponse($this->tokenBody('revoked', 'refresh-1'), $this->now));

        $this->client->pushRaw(401, '');
        $this->client->pushJson(200, $this->tokenBody('replacement', 'refresh-2'));
        $this->pushFixture('contact');

        $contact = $this->saasu(authentication: $oauth)->contacts()->get(54353);

        $this->assertSame(54353, $contact->id);
        $this->assertCount(3, $this->client->requests);
        $this->assertSame('/authorisation/refresh', $this->client->requests[1]->getUri()->getPath());
        $this->assertSame('Bearer replacement', $this->client->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testASecond401IsRaised(): void
    {
        $this->client->pushJson(200, $this->tokenBody('first'));
        $this->client->pushRaw(401, '');
        $this->client->pushJson(200, $this->tokenBody('second'));
        $this->client->pushRaw(401, '');

        $this->expectException(NotAuthenticatedException::class);

        try {
            $this->saasu(authentication: OAuth::password('api@example.com', 'secret', clock: $this->clock()))->contacts()->get(1);
        } finally {
            $this->assertCount(4, $this->client->requests);
        }
    }

    public function testAStaticCredentialIsNotRetriedOnA401(): void
    {
        $this->client->pushRaw(401, '');

        $this->expectException(NotAuthenticatedException::class);

        try {
            $this->saasu(authentication: new AccessToken('expired'))->contacts()->get(1);
        } finally {
            $this->assertCount(1, $this->client->requests);
        }
    }

    /**
     * Seeded from a grant with no password, a refused refresh has nowhere to go - and must not be
     * retried on every later request, each attempt spending quota on a known answer.
     */
    public function testASeededGrantWhoseRefreshIsRefusedRaisesAndIsNotRetried(): void
    {
        $seed = AccessGrant::fromResponse($this->tokenBody('old', 'dead-refresh'), $this->now->modify('-4 hours'));
        $saasu = $this->saasu(authentication: OAuth::grant($seed, clock: $this->clock()));

        $this->client->pushJson(401, ['error' => 'invalid_client', 'error_description' => 'Not a valid refresh token']);

        try {
            $saasu->contacts()->get(1);
            $this->fail('expected a NotAuthenticatedException');
        } catch (NotAuthenticatedException $e) {
            $this->assertSame('invalid_client', $e->oauthError());
        }

        try {
            $saasu->contacts()->get(1);
            $this->fail('expected a NotAuthenticatedException');
        } catch (NotAuthenticatedException $e) {
            $this->assertStringContainsString('not given a password', $e->getMessage());
        }

        $this->assertCount(1, $this->client->requests);
    }

    public function testAGrantReadsTheFileIdsOutOfItsScope(): void
    {
        $grant = AccessGrant::fromResponse($this->tokenBody(), $this->now);

        $this->assertSame([123, 456], $grant->fileIds());
        $this->assertTrue($grant->grantsFile(456));
        $this->assertFalse($grant->grantsFile(789));
    }

    public function testAGrantRoundTripsThroughItsPortableForm(): void
    {
        $grant = AccessGrant::fromResponse($this->tokenBody(), $this->now);
        $restored = AccessGrant::fromArray(Cast::object(json_decode((string) json_encode($grant->toArray()), true)));

        $this->assertSame('access-1', $restored->accessToken);
        $this->assertSame('refresh-1', $restored->refreshToken);
        $this->assertEquals($grant->expiresAt, $restored->expiresAt);
    }

    /**
     * json_encode() by accident must not leak the tokens; toArray() is the deliberate form.
     */
    public function testAGrantDoesNotSerialiseItsTokensByAccident(): void
    {
        $grant = AccessGrant::fromResponse($this->tokenBody('eyJsecretaccess', 'eyJsecretrefresh'), $this->now);

        $this->assertStringNotContainsString('eyJsecret', (string) json_encode($grant));
        $this->assertStringNotContainsString('eyJsecret', print_r($grant, true));
    }

    /**
     * Saasu's own authentication page shows a token_type of "example"; the header is always Bearer.
     */
    public function testTheSchemeIsAlwaysBearer(): void
    {
        $body = $this->tokenBody();
        $body['token_type'] = 'example';
        $this->client->pushJson(200, $body);
        $this->pushFixture('contact');

        $this->saasu(authentication: OAuth::password('api@example.com', 'secret', clock: $this->clock()))->contacts()->get(1);

        $this->assertSame('Bearer access-1', $this->client->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testAnEmptyCredentialIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WsAccessKey('   ');
    }
}

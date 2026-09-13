<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use Hampel\Saasu\Api\Authentication\AccessToken;
use Hampel\Saasu\Api\Authentication\OAuth;
use Hampel\Saasu\Api\Authentication\WsAccessKey;
use Hampel\Saasu\Api\Client;
use Hampel\Saasu\Api\Config;
use Hampel\Saasu\Api\Exception\RuntimeException;
use Hampel\Saasu\Api\Support\Psr17Discovery;
use Hampel\Saasu\Api\Throttle\NoThrottle;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Ported from xenforo-api, where the class was written.
 */
final class Psr17DiscoveryTest extends TestCase
{
    public function testAClientCanBeBuiltWithoutNamingAFactory(): void
    {
        $saasu = new Client(new Config(self::FILE_ID), new AccessToken('token'), $this->client, throttle: new NoThrottle());

        $this->assertInstanceOf(RequestFactoryInterface::class, $saasu->connection()->requestFactory());
        $this->assertInstanceOf(StreamFactoryInterface::class, $saasu->connection()->streamFactory());

        // and the found factory actually works end to end
        $this->pushFixture('contact');
        $this->assertSame(54353, $saasu->contacts()->get(54353)->id);
    }

    public function testOnThisMachineItFindsGuzzle(): void
    {
        [$request, $stream] = Psr17Discovery::find();

        $this->assertInstanceOf(HttpFactory::class, $request);
        $this->assertSame($request, $stream, 'Guzzle ships one class for both roles; it should be built once.');
    }

    public function testAFactoryThatIsGivenIsUsedRatherThanDiscovered(): void
    {
        $given = new HttpFactory();

        $saasu = new Client(new Config(self::FILE_ID), new AccessToken('token'), $this->client, $given, $given);

        $this->assertSame($given, $saasu->connection()->requestFactory());
        $this->assertSame($given, $saasu->connection()->streamFactory());
    }

    /**
     * The short forms name only the credential, the file and the transport - so they are the
     * constructors that depend on discovery most.
     */
    public function testTheShortFormsDiscoverTheirFactoriesAndCarryTheirCredential(): void
    {
        $oauth = Client::withOAuth('api@example.com', 'secret', self::FILE_ID, $this->client);
        $key = Client::withAccessKey('ABCDEF0123456789ABCDEF', self::FILE_ID, $this->client);

        foreach ([$oauth, $key] as $saasu) {
            $this->assertInstanceOf(RequestFactoryInterface::class, $saasu->connection()->requestFactory());
            $this->assertSame(self::FILE_ID, $saasu->config()->fileId);
        }

        $this->assertInstanceOf(OAuth::class, $oauth->authentication());
        $this->assertInstanceOf(WsAccessKey::class, $key->authentication());
    }

    /**
     * The not-found path cannot be reached with Guzzle installed, so it is reached through the
     * list instead.
     */
    public function testNothingFoundSaysWhatToPassAndWhatToInstall(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(RequestFactoryInterface::class);
        $this->expectExceptionMessage('guzzlehttp/psr7');

        Psr17Discovery::from([['No\Such\Factory', 'No\Such\Factory']]);
    }

    /**
     * A class that exists but is not a factory is skipped, not returned - the instanceof is what
     * makes discovery by name safe.
     */
    public function testAClassThatExistsButIsNotAFactoryIsSkipped(): void
    {
        $this->expectException(RuntimeException::class);

        Psr17Discovery::from([[\stdClass::class, \stdClass::class]]);
    }

    public function testLaterCandidatesAreTriedWhenEarlierOnesAreAbsent(): void
    {
        [$request] = Psr17Discovery::from([
            ['No\Such\Factory', 'No\Such\Factory'],
            [\stdClass::class, \stdClass::class],
            [HttpFactory::class, HttpFactory::class],
        ]);

        $this->assertInstanceOf(HttpFactory::class, $request);
    }
}

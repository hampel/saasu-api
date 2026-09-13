<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use Hampel\Saasu\Api\Authentication\AccessToken;
use Hampel\Saasu\Api\Authentication\Authentication;
use Hampel\Saasu\Api\Client;
use Hampel\Saasu\Api\Config;
use Hampel\Saasu\Api\Throttle\NoThrottle;
use Hampel\Saasu\Api\Throttle\Throttle;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class TestCase extends BaseTestCase
{
    public const FILE_ID = 123;

    protected StubClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new StubClient();
    }

    protected function saasu(
        ?Config $config = null,
        ?Authentication $authentication = null,
        ?LoggerInterface $logger = null,
        ?Throttle $throttle = null,
    ): Client {
        $factory = new HttpFactory();

        return new Client(
            $config ?? new Config(self::FILE_ID),
            $authentication ?? new AccessToken('test-access-token'),
            $this->client,
            $factory,
            $factory,
            $logger ?? new NullLogger(),
            $throttle ?? new NoThrottle(),
        );
    }

    /**
     * A sample response from Saasu's own API documentation, as a decoded array.
     *
     * @return array<string, mixed>
     */
    protected static function fixture(string $name): array
    {
        $decoded = json_decode((string) file_get_contents(__DIR__ . '/fixtures/' . $name . '.json'), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \LogicException(sprintf('Fixture %s is not an object.', $name));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    protected function pushFixture(string $name, int $status = 200): void
    {
        $this->client->pushJson($status, self::fixture($name));
    }

    /**
     * @return array<mixed>
     */
    protected function sentBody(): array
    {
        $decoded = json_decode((string) $this->client->lastRequest()->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function sentPath(): string
    {
        return $this->client->lastRequest()->getUri()->getPath();
    }

    protected function sentMethod(): string
    {
        return $this->client->lastRequest()->getMethod();
    }

    /**
     * The query string of the last request, decoded.
     *
     * @return array<string, string>
     */
    protected function sentQuery(): array
    {
        parse_str($this->client->lastRequest()->getUri()->getQuery(), $query);

        /** @var array<string, string> $query */
        return $query;
    }
}

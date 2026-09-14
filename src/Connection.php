<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api;

use Hampel\Saasu\Api\Authentication\Anonymous;
use Hampel\Saasu\Api\Authentication\Authentication;
use Hampel\Saasu\Api\Exception\ApiException;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Exception\MalformedResponseException;
use Hampel\Saasu\Api\Exception\RequestBudgetExhaustedException;
use Hampel\Saasu\Api\Exception\RequestException;
use Hampel\Saasu\Api\Exception\RuntimeException;
use Hampel\Saasu\Api\Result\ApiResponse;
use Hampel\Saasu\Api\Result\Document;
use Hampel\Saasu\Api\Result\ResponseMeta;
use Hampel\Saasu\Api\Support\Json;
use Hampel\Saasu\Api\Support\Redact;
use Hampel\Saasu\Api\Support\RequestCounter;
use Hampel\Saasu\Api\Throttle\Throttle;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Everything that touches HTTP, in one place.
 *
 * The client is injected as a PSR-18 ClientInterface rather than a concrete one. A host
 * application that routes all outbound traffic through its own stack - for a proxy, for SSRF
 * protection, for `Http::fake()` in a Laravel test - implements sendRequest() over it and
 * shares this code, instead of writing a second Saasu client because this one hard-coded the
 * wrong library.
 *
 * FOUR THINGS HAPPEN TO EVERY REQUEST, in this order: the file id is added to the query string,
 * the credential is applied, the request budget is checked, and the throttle waits. Applying an
 * OAuth credential may first mean fetching a token - and that fetch is a request of its own,
 * which passes the same budget and throttle before the one it was fetched for.
 *
 * The extension point of last resort - anything the endpoints do not wrap:
 *
 *     $saasu->connection()->get('Contacts', ['IsCustomer' => true])->collection('Contacts');
 */
final class Connection
{
    public const JSON_CONTENT_TYPE = 'application/json';

    public const FILE_PARAMETER = 'FileId';

    public function __construct(
        private readonly Config $config,
        private readonly Authentication $authentication,
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly Throttle $throttle,
        private readonly RequestCounter $counter = new RequestCounter(),
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $inFile = true,
    ) {
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function authentication(): Authentication
    {
        return $this->authentication;
    }

    public function client(): ClientInterface
    {
        return $this->client;
    }

    public function requestFactory(): RequestFactoryInterface
    {
        return $this->requestFactory;
    }

    public function streamFactory(): StreamFactoryInterface
    {
        return $this->streamFactory;
    }

    public function throttle(): Throttle
    {
        return $this->throttle;
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    public function counter(): RequestCounter
    {
        return $this->counter;
    }

    /**
     * How many requests this connection and every connection sharing its counter have sent,
     * token requests and retries included.
     */
    public function requestsSent(): int
    {
        return $this->counter->count();
    }

    /**
     * The same transport, throttle and counter, sending no credential and no file id - for the
     * token endpoints and a password reset.
     */
    public function unauthenticated(): self
    {
        return new self(
            $this->config,
            new Anonymous(),
            $this->client,
            $this->requestFactory,
            $this->streamFactory,
            $this->throttle,
            $this->counter,
            $this->logger,
            false
        );
    }

    /**
     * The same connection, not adding a file id - for the list of files the login can reach. Not
     * for `User` or `authorisation/ping`, both of which answer 401 without one.
     */
    public function withoutFile(): self
    {
        return new self(
            $this->config,
            $this->authentication,
            $this->client,
            $this->requestFactory,
            $this->streamFactory,
            $this->throttle,
            $this->counter,
            $this->logger,
            false
        );
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    public function get(string $path, array $query = []): ApiResponse
    {
        return $this->send($this->request('GET', $path, $query));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, scalar|null>  $query
     */
    public function post(string $path, array $payload = [], array $query = []): ApiResponse
    {
        return $this->send($this->withJson($this->request('POST', $path, $query), $payload));
    }

    /**
     * A Saasu PUT carries the whole record, `LastUpdatedId` included - see Request\Payload for
     * why the request builders are seeded from the record being updated.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, scalar|null>  $query
     */
    public function put(string $path, array $payload = [], array $query = []): ApiResponse
    {
        return $this->send($this->withJson($this->request('PUT', $path, $query), $payload));
    }

    /**
     * Saasu documents a delete answering 200 with a `StatusMessage`, not a 204.
     *
     * @param  array<string, scalar|null>  $query
     */
    public function delete(string $path, array $query = []): ApiResponse
    {
        return $this->send($this->request('DELETE', $path, $query));
    }

    /**
     * A GET whose answer is a file rather than JSON - the `generate-pdf` endpoints.
     *
     * @param  array<string, scalar|null>  $query
     */
    public function document(string $path, array $query = []): Document
    {
        $request = $this->request('GET', $path, $query)->withHeader('Accept', 'application/pdf, */*');
        [$response, $body, $sent] = $this->exchange($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            return Document::fromMeta($body, ResponseMeta::fromResponse($response));
        }

        throw $this->failure($sent, $response, $body);
    }

    /**
     * Follow a URI out of a response's `_links`.
     *
     * REFUSED IF IT DOES NOT POINT AT THE CONFIGURED API. The link came out of a response body,
     * and it is requested with the credential attached - which for a web services access key
     * means in the query string of a URL naming whatever host the link named.
     */
    public function follow(string $uri): ApiResponse
    {
        if (!$this->config->ownsUri($uri)) {
            $this->logger->error('Saasu API returned a link pointing somewhere else', [
                'uri' => Redact::uri($uri),
                'expected_host' => $this->config->host(),
            ]);

            throw new RuntimeException(sprintf(
                'Refusing to follow "%s": the Saasu API is configured as %s, and a followed link is '
                    . 'requested with the credential attached.',
                Redact::uri($uri),
                $this->config->baseUri
            ));
        }

        return $this->send($this->request('GET', $uri));
    }

    /**
     * Build a request without sending it. The file id, Accept and X-Api-Version are applied;
     * the credential is not, because applying it may mean fetching a token - send() does that.
     *
     * @param  array<string, scalar|null>  $query
     */
    public function request(string $method, string $path, array $query = []): RequestInterface
    {
        if ($this->inFile && !self::hasParameter($path, $query, self::FILE_PARAMETER)) {
            if ($this->config->fileId === null) {
                throw new InvalidArgumentException(sprintf(
                    'Saasu needs to know which file %s %s acts on, and this client was configured '
                        . 'without a file id. Pass one to Config, or use Client::withFileId().',
                    $method,
                    $path
                ));
            }

            $query = [self::FILE_PARAMETER => $this->config->fileId] + $query;
        }

        $request = $this->requestFactory
            ->createRequest($method, $this->config->resolve($path, $query))
            ->withHeader('Accept', self::JSON_CONTENT_TYPE);

        return $this->config->apiVersion === null
            ? $request
            : $request->withHeader('X-Api-Version', $this->config->apiVersion);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function withJson(RequestInterface $request, array $payload): RequestInterface
    {
        return $request
            ->withHeader('Content-Type', self::JSON_CONTENT_TYPE)
            ->withBody($this->streamFactory->createStream(Json::encode($payload)));
    }

    /**
     * Send a request built elsewhere, with this connection's credential and error handling.
     */
    public function send(RequestInterface $request): ApiResponse
    {
        [$response, $body, $sent] = $this->exchange($request);

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw $this->failure($sent, $response, $body);
        }

        $meta = ResponseMeta::fromResponse($response);
        [$decoded, $value] = Json::decode($body);

        if ($decoded) {
            return new ApiResponse($value, $status, $meta);
        }

        // A 204 is empty on purpose, and so is a write that answered with nothing. A GET that
        // answered with nothing is not: read as an empty list it would reach the caller as
        // "this file has no unpaid invoices".
        if ($status === 204 || (trim($body) === '' && $sent->getMethod() !== 'GET')) {
            return new ApiResponse(null, $status, $meta);
        }

        $uri = Redact::uri((string) $sent->getUri());

        $this->logger->error('Saasu API answered success with a body that is not JSON', [
            'method' => $sent->getMethod(),
            'uri' => $uri,
            'status' => $status,
            'content_type' => $response->getHeaderLine('Content-Type'),
        ]);

        throw MalformedResponseException::forResponse($sent->getMethod(), $uri, $response, $body);
    }

    /**
     * A URI with any credential in it masked - for anything about to be logged or shown.
     */
    public static function redact(string $uri): string
    {
        return Redact::uri($uri);
    }

    /**
     * Authenticate, send, and on a 401 give the credential one chance to recover.
     *
     * ONE RETRY, AND ONLY FOR A BODILESS 401. A 401 means Saasu refused the request before doing
     * anything with it, so sending a POST again cannot create a second record. Nothing else is
     * retried here: a timeout on an insert may have been written, and deciding what to do about
     * that belongs to the caller.
     *
     * @return array{ResponseInterface, string, RequestInterface}  the response, its body, and
     *         the request as actually sent
     */
    private function exchange(RequestInterface $request): array
    {
        $sent = $this->authentication->authenticate($request, $this);
        $response = $this->dispatch($sent);
        $body = (string) $response->getBody();

        // ONLY A BODILESS 401 IS A REFUSED CREDENTIAL. That is the shape measured for a missing,
        // invalid or expired token. A 401 that explains itself is about something else - measured
        // 2026-09-14, `GET User` with no file id answers 401 "No FileId or Invalid FileId
        // supplied." - and refreshing the token for it spends two requests on the same answer.
        if ($response->getStatusCode() === 401 && trim($body) === '' && $this->authentication->recover($this)) {
            $this->logger->info('Saasu API refused the credential; retrying once with a fresh one', [
                'method' => $request->getMethod(),
                'uri' => Redact::uri((string) $request->getUri()),
                'credential' => $this->authentication->describe(),
            ]);

            $stream = $request->getBody();

            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            $sent = $this->authentication->authenticate($request, $this);
            $response = $this->dispatch($sent);
            $body = (string) $response->getBody();
        }

        return [$response, $body, $sent];
    }

    private function failure(RequestInterface $sent, ResponseInterface $response, string $body): ApiException
    {
        $uri = Redact::uri((string) $sent->getUri());

        $this->logger->error('Saasu API error response', [
            'method' => $sent->getMethod(),
            'uri' => $uri,
            'status' => $response->getStatusCode(),
            'body' => substr($body, 0, 2000),
        ]);

        return ApiException::fromResponse($sent->getMethod(), $uri, $response, $body);
    }

    /**
     * Count it, throttle it, send it, and keep a transport failure distinct from an HTTP status.
     *
     * The catch is ClientExceptionInterface and not \Throwable, deliberately. Laravel's
     * StrayRequestException, raised by `Http::preventStrayRequests()`, is a plain
     * RuntimeException, and it reaches the consumer's test naming the URL only because it
     * passes through here untouched.
     */
    private function dispatch(RequestInterface $request): ResponseInterface
    {
        $budget = $this->config->requestBudget;

        if ($budget !== null && $this->counter->count() >= $budget) {
            throw RequestBudgetExhaustedException::after($budget);
        }

        $method = $request->getMethod();
        $uri = Redact::uri((string) $request->getUri());

        $this->throttle->wait();
        $this->counter->increment();

        $this->logger->debug('Saasu API request', ['method' => $method, 'uri' => $uri]);

        try {
            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('Saasu API request failed', [
                'method' => $method,
                'uri' => $uri,
                'error' => Redact::uri($e->getMessage()),
            ]);

            throw RequestException::for($method, $uri, $e);
        }
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    private static function hasParameter(string $path, array $query, string $name): bool
    {
        foreach (array_keys($query) as $key) {
            if (strcasecmp($key, $name) === 0) {
                return true;
            }
        }

        $existing = parse_url($path, PHP_URL_QUERY);

        return is_string($existing) && preg_match('/(^|&)' . preg_quote($name, '/') . '=/i', $existing) === 1;
    }
}

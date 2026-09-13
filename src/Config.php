<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api;

use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Result\Page;

/**
 * Which Saasu file we are working in, and how requests are shaped.
 *
 *     new Config(fileId: 12345)
 *
 * THE FILE ID IS THE ONE THING THAT MATTERS. A Saasu "file" is one set of books - one business -
 * and nearly every request names the file it acts on as a `FileId` query parameter. One login
 * can reach several files, which is why the file belongs to the configuration rather than to
 * the credential: `Client::withFileId()` moves a client to another file without
 * re-authenticating. A request that needs a file on a client configured without one is refused
 * before it is sent.
 *
 * Everything else is optional and plain values, so an application's config array maps onto it
 * directly.
 */
final class Config
{
    public const DEFAULT_BASE_URI = 'https://api.saasu.com';

    /**
     * The API version sent as `X-Api-Version` on every request.
     *
     * Saasu recommends pinning it: a call that names a version keeps getting that version's
     * behaviour after a breaking change, for as long as Saasu supports it (at most two prior
     * versions). A call that names none gets whatever is current. `1.0` is the only version
     * there has been.
     */
    public const API_VERSION = '1.0';

    public readonly string $baseUri;

    /**
     * @param  int|null  $fileId  the Saasu file to act on. Required by every endpoint except the
     *                            user, file-list and token ones
     * @param  int  $pageSize  items per list request, 1 to 100. The default is Saasu's MAXIMUM
     *                         rather than its own default of 25, because every page is a request
     *                         against a daily quota: walking 1,000 contacts costs 10 requests at
     *                         100 and 40 at 25
     * @param  string|null  $apiVersion  sent as `X-Api-Version`; null sends no header
     * @param  int|null  $requestBudget  the most requests this client - and every client derived
     *                                   from it - may send before refusing. Null is unlimited. See
     *                                   RequestBudgetExhaustedException for why this exists
     * @param  string|null  $baseUri  for a recorded fixture or an outbound proxy, and nothing else
     */
    public function __construct(
        public readonly ?int $fileId = null,
        public readonly int $pageSize = Page::MAX_PAGE_SIZE,
        public readonly ?string $apiVersion = self::API_VERSION,
        public readonly ?int $requestBudget = null,
        ?string $baseUri = null,
    ) {
        if ($fileId !== null && $fileId < 1) {
            throw new InvalidArgumentException(sprintf('A Saasu file id must be a positive integer; %d was given.', $fileId));
        }

        Page::assertValidPageSize($pageSize);

        if ($requestBudget !== null && $requestBudget < 1) {
            throw new InvalidArgumentException('A request budget must allow at least one request; use null for no budget.');
        }

        if ($apiVersion !== null && preg_match('/^\d+\.\d+$/', $apiVersion) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Saasu expects X-Api-Version as MAJOR.MINOR, such as "%s"; "%s" is not.',
                self::API_VERSION,
                $apiVersion
            ));
        }

        $baseUri = trim($baseUri ?? self::DEFAULT_BASE_URI);

        if (!str_starts_with($baseUri, 'http://') && !str_starts_with($baseUri, 'https://')) {
            throw new InvalidArgumentException(sprintf(
                'The Saasu API base URI must be absolute, with a scheme: "%s" is not.',
                $baseUri
            ));
        }

        $this->baseUri = rtrim($baseUri, '/');
    }

    /**
     * The same configuration pointed at another file.
     */
    public function withFileId(?int $fileId): self
    {
        return new self($fileId, $this->pageSize, $this->apiVersion, $this->requestBudget, $this->baseUri);
    }

    public function withPageSize(int $pageSize): self
    {
        return new self($this->fileId, $pageSize, $this->apiVersion, $this->requestBudget, $this->baseUri);
    }

    public function withRequestBudget(?int $requestBudget): self
    {
        return new self($this->fileId, $this->pageSize, $this->apiVersion, $requestBudget, $this->baseUri);
    }

    /**
     * Turn a path into an absolute URI. An absolute URI passes through untouched, so a link out
     * of a response's `_links` can be fed straight back in.
     *
     * Query values of null are dropped, booleans are written `true`/`false` - which Saasu's
     * filters accept alongside `1`/`0`/`yes`/`no` - and everything else is RFC 3986 encoded.
     *
     * @param  array<string, scalar|null>  $query
     */
    public function resolve(string $path, array $query = []): string
    {
        $uri = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : $this->baseUri . '/' . ltrim($path, '/');

        $parameters = [];

        foreach ($query as $name => $value) {
            if ($value !== null) {
                $parameters[$name] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            }
        }

        if ($parameters !== []) {
            $uri .= (str_contains($uri, '?') ? '&' : '?') . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        }

        return $uri;
    }

    public function host(): string
    {
        return parse_url($this->baseUri, PHP_URL_HOST) ?: 'api.saasu.com';
    }

    /**
     * Whether a URI out of a response body points at the API we are configured for.
     *
     * A `_links` href is requested with the credential attached - and with the web services
     * access key, the credential is IN the query string - so following one that pointed
     * elsewhere would hand the key to whoever wrote the link. Scheme and host must both match.
     */
    public function ownsUri(string $uri): bool
    {
        $parts = parse_url($uri);
        $base = parse_url($this->baseUri);

        if (!is_array($parts) || !is_array($base)) {
            return false;
        }

        return isset($parts['host'], $base['host'])
            && strcasecmp($parts['host'], $base['host']) === 0
            && strcasecmp($parts['scheme'] ?? '', $base['scheme'] ?? '') === 0;
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Result;

use Psr\Http\Message\ResponseInterface;

/**
 * What arrived alongside a response body.
 *
 * Saasu documents no response headers - no rate-limit budget, no remaining daily quota, no
 * request id - so every header is kept rather than a few named. Keys are lowercased.
 */
final class ResponseMeta
{
    /**
     * @param  array<string, string>  $headers  lowercased name to comma-joined value
     */
    public function __construct(
        public readonly array $headers = [],
    ) {
    }

    public static function fromResponse(ResponseInterface $response): self
    {
        $headers = [];

        foreach (array_keys($response->getHeaders()) as $name) {
            $headers[strtolower((string) $name)] = $response->getHeaderLine((string) $name);
        }

        return new self($headers);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function contentType(): ?string
    {
        return $this->header('Content-Type');
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->headers;
    }
}

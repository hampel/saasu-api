<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

use Hampel\Saasu\Api\Support\Redact;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * The request never got an answer: DNS, TLS, a timeout, a refused connection.
 *
 * Distinct from every ApiException, all of which mean Saasu replied and the reply was not a
 * success. A retry is reasonable here and frequently is not for the others - but a create that
 * timed out may still have been written, and Saasu has no idempotency key. Look before
 * retrying an insert: a second invoice is a real document with a real number.
 *
 * The previous exception is chained as it was thrown, and an HTTP client's message usually
 * quotes the URL - with a web services access key, key and all. This message is redacted; the
 * chained one is the client's and is not rewritten.
 */
final class RequestException extends SaasuException
{
    public static function for(string $method, string $uri, ClientExceptionInterface $previous): self
    {
        return new self(
            sprintf('Could not reach the Saasu API for %s %s: %s', $method, $uri, Redact::uri($previous->getMessage())),
            0,
            $previous
        );
    }
}

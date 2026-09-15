<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

use Hampel\Saasu\Api\ErrorDetail;
use Psr\Http\Message\ResponseInterface;

/**
 * Saasu answered, and the answer was not a success.
 *
 * READ THE TYPE, THEN THE MESSAGES. Saasu reports most caller mistakes as a 400 whose body is a
 * sentence - sometimes a bare JSON string, sometimes an ASP.NET `{"Message": ...}` object - and
 * the subclasses below are the distinctions a caller can act on: a value that was refused, a
 * record that changed underneath an update, a credential that is not one, no such record.
 *
 * The parsed body is on `error`, which is nullable because a 401 on a data endpoint carries no
 * body at all.
 */
abstract class ApiException extends SaasuException
{
    /**
     * @param  string  $body  the raw response body, for when it was not JSON at all
     */
    final public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?ErrorDetail $error = null,
        public readonly string $body = '',
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * @param  string  $uri  already redacted - see Connection::redact()
     */
    public static function fromResponse(string $method, string $uri, ResponseInterface $response, string $body): self
    {
        $status = $response->getStatusCode();
        $error = ErrorDetail::fromBody($body);
        $detail = $error?->describe() ?? '';

        // Some rejections are PLAIN TEXT, neither JSON nor HTML - measured 2026-09-14, a
        // FileIdentity PUT answered 400 with a list of required fields as bare sentences.
        if ($detail === '' && trim($body) !== '' && !ErrorDetail::looksLikeHtml($body)) {
            $detail = trim((string) preg_replace('/\s+/', ' ', substr($body, 0, 500)));
        }

        if ($detail === '' && $status === 401) {
            $detail = 'the access token or web services access key was missing, expired or revoked (this status carries no body)';
        }

        if ($detail === '' && $status === 403) {
            $detail = 'the user this credential logs in as lacks the permission (this status often carries no body)';
        }

        if ($detail === '' && $status === 404 && ErrorDetail::looksLikeHtml($body)) {
            $detail = 'the server answered with an HTML error page, which is what an unrouted path looks like rather than a missing record';
        }

        $message = sprintf(
            'Saasu rejected %s %s (HTTP %d)%s',
            $method,
            $uri,
            $status,
            $detail === '' ? '' : ': ' . $detail
        );

        $retryAfter = self::retryAfter($response);

        return match (true) {
            // Measured 2026-09-14: a stale contact update answers 400, a stale invoice update 409,
            // both with the same sentence - so the sentence decides, not the status.
            ($status === 400 || $status === 409) && ($error?->isConcurrencyConflict() ?? false)
                => new ConcurrencyException($message, $status, $error, $body, $retryAfter),
            // Measured 2026-09-15: most refusals of a restricted user are 403, but TaxCodes answers
            // 400 with a sentence saying so.
            $status === 400 && ($error?->isPermissionRefusal() ?? false)
                => new NotPermittedException($message, $status, $error, $body, $retryAfter),
            $status === 400 => new ValidationException($message, $status, $error, $body, $retryAfter),
            $status === 401 && $error !== null && $error->twoFactorCodeRequired
                => new TwoFactorRequiredException($message, $status, $error, $body, $retryAfter),
            $status === 401 => new NotAuthenticatedException($message, $status, $error, $body, $retryAfter),
            $status === 403 => new NotPermittedException($message, $status, $error, $body, $retryAfter),
            $status === 404 => new NotFoundException($message, $status, $error, $body, $retryAfter),
            $status === 429 => new TooManyRequestsException($message, $status, $error, $body, $retryAfter),
            $status >= 500 => new ServerException($message, $status, $error, $body, $retryAfter),
            default => new ClientException($message, $status, $error, $body, $retryAfter),
        };
    }

    /**
     * Every message Saasu gave, in order. Empty for a status that carried no body.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        return $this->error?->messages() ?? [];
    }

    /**
     * Rejected fields keyed by name, when the body carried an ASP.NET `ModelState`.
     *
     * Most of Saasu's rejections do NOT populate this - they are a sentence about the request
     * rather than a map of fields - so an empty array is not "nothing was wrong with a field".
     *
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return $this->error->fieldErrors ?? [];
    }

    /**
     * Whether any message contains this text, case-insensitively - the only way to branch on a
     * rejection Saasu reports as prose.
     */
    public function mentions(string $text): bool
    {
        foreach ($this->messages() as $message) {
            if (stripos($message, $text) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function retryAfter(ResponseInterface $response): ?int
    {
        $header = trim($response->getHeaderLine('Retry-After'));

        return $header !== '' && ctype_digit($header) ? (int) $header : null;
    }
}

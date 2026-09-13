<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api;

use Hampel\Saasu\Api\Support\Cast;
use Hampel\Saasu\Api\Support\Json;

/**
 * The body of a failed request, parsed from whichever of Saasu's shapes it arrived in.
 *
 * THERE IS NO ONE ERROR FORMAT, and none is documented. Four shapes reach this class:
 *
 *  - A BARE JSON STRING, which is how Saasu's own .NET client tests show a rejected update:
 *    `"Record to be updated has changed since last read."`
 *  - AN ASP.NET WEB API ERROR OBJECT, `{"Message": "...", "ModelState": {"field": ["..."]}}`,
 *    the framework's default for a model that did not bind.
 *  - AN OAUTH ERROR, from the token endpoints - measured 2026-09-13:
 *    `{"error": "invalid_credentials", "error_description": "Invalid username or password",
 *    "data": null, "is_2fa_code_required": false, "is_2fa_code_sent": false}`
 *  - A LIST of any of the above.
 *
 * And two that are not JSON at all: nothing, which is every 401 from a data endpoint, and an
 * IIS HTML page, which is a 404 on a path Saasu does not route. Both answer null from
 * fromBody().
 *
 * Defensive at every level, because this runs on the failure path: an exception raised while
 * building an exception loses the original failure.
 */
final class ErrorDetail implements \JsonSerializable
{
    /**
     * The sentence Saasu uses for a stale `LastUpdatedId`.
     */
    public const CONCURRENCY_MARKER = 'changed since last read';

    /**
     * @param  list<string>  $messages  every sentence the body carried, in order
     * @param  array<string, list<string>>  $fieldErrors  an ASP.NET ModelState, when there was one
     */
    public function __construct(
        public readonly array $messages = [],
        public readonly array $fieldErrors = [],
        public readonly ?string $oauthError = null,
        public readonly bool $twoFactorCodeRequired = false,
        public readonly bool $twoFactorCodeSent = false,
        public readonly mixed $raw = null,
    ) {
    }

    public static function fromBody(string $body): ?self
    {
        [$decoded, $value] = Json::decode($body);

        return $decoded ? self::from($value) : null;
    }

    public static function from(mixed $value): ?self
    {
        if ($value === null || $value === [] || $value === '') {
            return null;
        }

        $messages = [];
        $fields = [];
        $oauthError = null;
        $required = false;
        $sent = false;

        $items = is_array($value) && array_is_list($value) ? $value : [$value];

        foreach ($items as $item) {
            if (is_scalar($item)) {
                $messages[] = (string) $item;

                continue;
            }

            $object = Cast::object($item);

            foreach (['Message', 'message', 'ExceptionMessage', 'MessageDetail', 'error_description'] as $key) {
                $text = Cast::text($object[$key] ?? null);

                if ($text !== null && !in_array($text, $messages, true)) {
                    $messages[] = $text;
                }
            }

            foreach (Cast::object($object['ModelState'] ?? null) as $field => $fieldMessages) {
                $list = is_array($fieldMessages) ? Cast::strings($fieldMessages) : Cast::strings([$fieldMessages]);

                if ($list !== []) {
                    // ASP.NET prefixes the bound parameter's name - `contact.EmailAddress` -
                    // which is the server's variable, not anything the caller sent.
                    $name = str_contains($field, '.') ? substr($field, strrpos($field, '.') + 1) : $field;
                    $fields[$name] = [...($fields[$name] ?? []), ...$list];
                }
            }

            $oauthError ??= Cast::text($object['error'] ?? null);
            $required = $required || (Cast::bool($object['is_2fa_code_required'] ?? null) ?? false);
            $sent = $sent || (Cast::bool($object['is_2fa_code_sent'] ?? null) ?? false);
        }

        if ($messages === [] && $fields === [] && $oauthError === null) {
            return null;
        }

        return new self($messages, $fields, $oauthError, $required, $sent, $value);
    }

    /**
     * Whether a body that failed to decode is an HTML page - an IIS or Cloudflare error
     * document rather than anything the API wrote.
     */
    public static function looksLikeHtml(string $body): bool
    {
        $start = strtolower(ltrim(substr($body, 0, 200)));

        return str_starts_with($start, '<!doctype html') || str_starts_with($start, '<html');
    }

    /**
     * Every message, then every field message, in order.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        $all = $this->messages;

        foreach ($this->fieldErrors as $field => $messages) {
            foreach ($messages as $message) {
                $all[] = $field . ': ' . $message;
            }
        }

        return $all;
    }

    public function isConcurrencyConflict(): bool
    {
        foreach ($this->messages as $message) {
            if (stripos($message, self::CONCURRENCY_MARKER) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * One line, for an exception message or a log.
     *
     * .NET exception text arrives with `\r\n` inside it - "...an Income Account assigned to
     * it.\r\nParameter name: SaleIncomeAccountUid" - which is flattened here so a log line stays
     * one line.
     */
    public function describe(): string
    {
        $messages = $this->messages();

        if ($messages === [] && $this->oauthError !== null) {
            $messages = [$this->oauthError];
        }

        return trim((string) preg_replace('/\s*[\r\n]+\s*/', ' ', implode('; ', $messages)));
    }

    public function jsonSerialize(): mixed
    {
        return $this->raw;
    }
}

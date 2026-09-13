<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

/**
 * No such record - HTTP 404. Or no such PATH, which is a different problem with the same status.
 *
 * Measured 2026-09-13: a path Saasu does not route answers 404 with an IIS HTML error page,
 * not JSON. isUnroutedPath() tells the two apart, and Endpoint::apiFind() raises rather than
 * answering null for the second - a typo in a custom endpoint's path is not "that record does
 * not exist".
 */
final class NotFoundException extends ApiException
{
    public function isUnroutedPath(): bool
    {
        return \Hampel\Saasu\Api\ErrorDetail::looksLikeHtml($this->body);
    }
}

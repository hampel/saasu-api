<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

/**
 * Saasu failed - HTTP 5xx. Not the caller's mistake, and not necessarily a transient one.
 */
final class ServerException extends ApiException
{
}

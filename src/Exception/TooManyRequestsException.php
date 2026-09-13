<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

/**
 * HTTP 429.
 *
 * MAPPED DEFENSIVELY - Saasu documents its limits (one request a second; a daily quota between
 * 3,000 and 8,000 by plan) but not the response that enforces them, and nothing here has
 * observed one. If the daily limit was the one hit, waiting a second will not help: access is
 * blocked for 24 hours from that moment.
 */
final class TooManyRequestsException extends ApiException
{
}

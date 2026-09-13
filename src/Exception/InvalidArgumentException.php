<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

/**
 * The caller asked for something that cannot be sent - half of a date range, a page size above
 * the 100 Saasu accepts, an update with no LastUpdatedId.
 *
 * Raised before a request is made, which is the point of it. On this API that is worth more
 * than usual: every request counts against a daily quota, and a mistake the API has to reject
 * still spends one.
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
}

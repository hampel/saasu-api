<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

/**
 * Saasu understood the credential and refused the operation - HTTP 403.
 */
final class NotPermittedException extends ApiException
{
}

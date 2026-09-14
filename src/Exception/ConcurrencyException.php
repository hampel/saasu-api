<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

/**
 * The record changed after it was read, so the update was refused - "Record to be updated has
 * changed since last read." Measured 2026-09-14 as HTTP 400 on a contact and HTTP 409 on an
 * invoice.
 *
 * Every update on this API carries the `LastUpdatedId` the record had when it was read, and
 * Saasu refuses the write when that is no longer current. The fix is always the same - read
 * the record again, reapply the change, send it with the new id - and never to retry the same
 * request, which will fail identically.
 *
 * Not a ValidationException, although the status is the same: nothing about the request was
 * wrong, and a form that shows validation messages is the wrong place for this one.
 */
final class ConcurrencyException extends ApiException
{
}

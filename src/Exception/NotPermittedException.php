<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

/**
 * Saasu understood the credential and refused the operation, because the user it logs in as lacks
 * the permission.
 *
 * FIVE SHAPES ARE RECOGNISED, measured 2026-09-15 and 2026-11-18 with restricted users: HTTP 403
 * with no body (most reads, an account or sale insert), 403 with the JSON string "Not authorised"
 * (items), 403 with the string "Sorry, you do not have permission ..." (a payment insert), and
 * **400** with a `Message` saying the same (tax codes, a contact delete) - which is recognised by
 * `ErrorDetail::PERMISSION_MARKER`.
 *
 * A SIXTH IS NOT: payments answer such a user 400 "Value cannot be null. Parameter name:
 * collection", which arrives as a ValidationException because nothing in it names a permission -
 * and a payment list missing a required date range answers the same sentence.
 */
final class NotPermittedException extends ApiException
{
}

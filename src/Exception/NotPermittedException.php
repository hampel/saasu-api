<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

/**
 * Saasu understood the credential and refused the operation, because the user it logs in as lacks
 * the permission.
 *
 * THREE SHAPES, measured 2026-09-15 with a user restricted to contacts: HTTP 403 with no body
 * (accounts, invoices, journals, and an account insert), 403 with the JSON string
 * "Not authorised" (items), and **400** with a `Message` saying "you do not have permission"
 * (tax codes) - which is recognised by `ErrorDetail::PERMISSION_MARKER`.
 *
 * A FOURTH IS NOT RECOGNISED: payments answered such a user 400 "Value cannot be null. Parameter
 * name: collection", which arrives as a ValidationException because nothing in it names a
 * permission.
 */
final class NotPermittedException extends ApiException
{
}

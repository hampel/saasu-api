<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

/**
 * Saasu refused a value in the request - HTTP 400.
 *
 * The commonest failure and usually the only one fixable from what came back, but NOT a field
 * map. Saasu's 400s are mostly sentences - "Please specify AdjustmentItems for this
 * transaction.", "You already have an item with code ABC" - and some carry the .NET exception
 * text they came from, internal property names included ("Parameter name:
 * SaleIncomeAccountUid", for a field this API calls `SaleIncomeAccountId`). Show messages();
 * do not expect fieldErrors() to be populated.
 */
final class ValidationException extends ApiException
{
}

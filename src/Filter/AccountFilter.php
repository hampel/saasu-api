<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Filter;

use Hampel\Saasu\Api\Enum\AccountLevel;
use Hampel\Saasu\Api\Enum\AccountType;

/**
 * Filters for `Accounts`.
 */
final class AccountFilter extends Filter
{
    public function active(bool $active = true): static
    {
        return $this->withParameter('IsActive', $active);
    }

    public function bankAccounts(bool $bankAccounts = true): static
    {
        return $this->withParameter('IsBankAccount', $bankAccounts);
    }

    public function ofType(AccountType $type): static
    {
        return $this->withParameter('AccountType', $type->value);
    }

    /**
     * Header or detail accounts. Saasu documents the filter lower case - `header`, `detail` - where the field reads `Header`.
     */
    public function ofLevel(AccountLevel $level): static
    {
        return $this->withParameter('AccountLevel', strtolower($level->value));
    }

    public function includeBuiltIn(bool $include = true): static
    {
        return $this->withParameter('IncludeBuiltIn', $include);
    }

    public function underHeader(int $headerAccountId): static
    {
        return $this->withParameter('HeaderAccountId', $headerAccountId);
    }
}

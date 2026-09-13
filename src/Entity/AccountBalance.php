<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * The balance of one bank account, from `Accounts/BankAccountBalances`.
 *
 * Documented only by a generated sample - Saasu's own page for this model fails to render - so the
 * four fields here are the four that sample carries.
 */
final class AccountBalance extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $accountId = null,
        public readonly ?string $accountName = null,
        public readonly ?string $accountType = null,
        public readonly ?float $balance = null,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): static
    {
        return new self(
            Cast::int($row['AccountId'] ?? null),
            Cast::text($row['AccountName'] ?? null),
            Cast::text($row['AccountType'] ?? null),
            Cast::float($row['Balance'] ?? null),
            $row,
        );
    }
}

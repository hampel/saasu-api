<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Enum\AccountLevel;
use Hampel\Saasu\Api\Enum\AccountType;
use Hampel\Saasu\Api\Support\Cast;

/**
 * An account in the chart of accounts - income, expense, asset, a bank account.
 *
 * TWO LEVELS. A `Header` account groups others and cannot be posted to; a `Detail` account is
 * what a transaction line names. `HeaderAccountId` is the detail account's parent.
 *
 * A BANK ACCOUNT IS AN ACCOUNT with `IsBankAccount` set, and only then are the BSB, number and
 * bank-file fields meaningful. `IsBuiltIn` marks the ones the subscription created, which Saasu
 * will not let you delete.
 */
final class Account extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $accountLevel = null,
        public readonly ?string $accountType = null,
        public readonly ?bool $isActive = null,
        public readonly ?bool $isBuiltIn = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?string $defaultTaxCode = null,
        public readonly ?string $ledgerCode = null,
        public readonly ?string $currency = null,
        public readonly ?int $headerAccountId = null,
        public readonly ?int $exchangeAccountId = null,
        public readonly ?bool $isBankAccount = null,
        public readonly ?\DateTimeImmutable $createdDateUtc = null,
        public readonly ?\DateTimeImmutable $lastModifiedDateUtc = null,
        public readonly ?bool $includeInForecaster = null,
        public readonly ?string $bsb = null,
        public readonly ?string $number = null,
        public readonly ?string $bankAccountName = null,
        public readonly ?bool $bankFileCreationEnabled = null,
        public readonly ?string $bankCode = null,
        public readonly ?string $userNumber = null,
        public readonly ?int $merchantFeeAccountId = null,
        public readonly ?bool $includePendingTransactions = null,
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
            Cast::int($row['Id'] ?? null),
            Cast::text($row['Name'] ?? null),
            Cast::text($row['AccountLevel'] ?? null),
            Cast::text($row['AccountType'] ?? null),
            Cast::bool($row['IsActive'] ?? null),
            Cast::bool($row['IsBuiltIn'] ?? null),
            Cast::text($row['LastUpdatedId'] ?? null),
            Cast::text($row['DefaultTaxCode'] ?? null),
            Cast::text($row['LedgerCode'] ?? null),
            Cast::text($row['Currency'] ?? null),
            Cast::int($row['HeaderAccountId'] ?? null),
            Cast::int($row['ExchangeAccountId'] ?? null),
            Cast::bool($row['IsBankAccount'] ?? null),
            Cast::datetime($row['CreatedDateUtc'] ?? null),
            Cast::datetime($row['LastModifiedDateUtc'] ?? null),
            Cast::bool($row['IncludeInForecaster'] ?? null),
            Cast::text($row['BSB'] ?? null),
            Cast::text($row['Number'] ?? null),
            Cast::text($row['BankAccountName'] ?? null),
            Cast::bool($row['BankFileCreationEnabled'] ?? null),
            Cast::text($row['BankCode'] ?? null),
            Cast::text($row['UserNumber'] ?? null),
            Cast::int($row['MerchantFeeAccountId'] ?? null),
            Cast::bool($row['IncludePendingTransactions'] ?? null),
            $row,
        );
    }

    public function type(): ?AccountType
    {
        return Cast::enum(AccountType::class, $this->accountType);
    }

    public function level(): ?AccountLevel
    {
        return Cast::enum(AccountLevel::class, $this->accountLevel);
    }

    /**
     * Whether a transaction line can be posted to this account - a header account only groups.
     */
    public function isPostable(): bool
    {
        return $this->level() !== AccountLevel::Header;
    }
}

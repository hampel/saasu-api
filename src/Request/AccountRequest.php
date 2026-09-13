<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Enum\AccountLevel;
use Hampel\Saasu\Api\Enum\AccountType;
use Hampel\Saasu\Api\Support\Cast;

/**
 * An account to insert, or the changes to one.
 *
 *     AccountRequest::of('Consulting income', AccountType::Income)
 */
final class AccountRequest extends Payload
{
    protected const WRITABLE = [
        'Name',
        'AccountType',
        'AccountLevel',
        'IsActive',
        'DefaultTaxCode',
        'LedgerCode',
        'Currency',
        'HeaderAccountId',
        'ExchangeAccountId',
        'IsBankAccount',
        'IncludeInForecaster',
        'BSB',
        'Number',
        'BankAccountName',
        'BankFileCreationEnabled',
        'BankCode',
        'UserNumber',
        'MerchantFeeAccountId',
        'LastUpdatedId',
    ];

    public static function of(string $name, AccountType $type, AccountLevel $level = AccountLevel::Detail): self
    {
        return self::make()
            ->withName(Cast::required($name, 'An account needs a name.'))
            ->withAccountType($type)
            ->withAccountLevel($level);
    }

    public function withName(?string $name): static
    {
        return $this->with('Name', $name);
    }

    public function withAccountType(AccountType|string|null $accountType): static
    {
        return $this->with('AccountType', $accountType instanceof \BackedEnum ? $accountType->value : $accountType);
    }

    public function withAccountLevel(AccountLevel|string|null $accountLevel): static
    {
        return $this->with('AccountLevel', $accountLevel instanceof \BackedEnum ? $accountLevel->value : $accountLevel);
    }

    public function withActive(?bool $active): static
    {
        return $this->with('IsActive', $active);
    }

    public function withDefaultTaxCode(?string $defaultTaxCode): static
    {
        return $this->with('DefaultTaxCode', $defaultTaxCode);
    }

    public function withLedgerCode(?string $ledgerCode): static
    {
        return $this->with('LedgerCode', $ledgerCode);
    }

    public function withCurrency(?string $currency): static
    {
        return $this->with('Currency', $currency);
    }

    public function withHeaderAccountId(?int $headerAccountId): static
    {
        return $this->with('HeaderAccountId', $headerAccountId);
    }

    public function withExchangeAccountId(?int $exchangeAccountId): static
    {
        return $this->with('ExchangeAccountId', $exchangeAccountId);
    }

    public function withBankAccount(?bool $bankAccount): static
    {
        return $this->with('IsBankAccount', $bankAccount);
    }

    public function withIncludeInForecaster(?bool $includeInForecaster): static
    {
        return $this->with('IncludeInForecaster', $includeInForecaster);
    }

    public function withBsb(?string $bsb): static
    {
        return $this->with('BSB', $bsb);
    }

    public function withAccountNumber(?string $accountNumber): static
    {
        return $this->with('Number', $accountNumber);
    }

    public function withBankAccountName(?string $bankAccountName): static
    {
        return $this->with('BankAccountName', $bankAccountName);
    }

    public function withBankFileCreationEnabled(?bool $bankFileCreationEnabled): static
    {
        return $this->with('BankFileCreationEnabled', $bankFileCreationEnabled);
    }

    public function withBankCode(?string $bankCode): static
    {
        return $this->with('BankCode', $bankCode);
    }

    public function withUserNumber(?string $userNumber): static
    {
        return $this->with('UserNumber', $userNumber);
    }

    public function withMerchantFeeAccountId(?int $merchantFeeAccountId): static
    {
        return $this->with('MerchantFeeAccountId', $merchantFeeAccountId);
    }

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}

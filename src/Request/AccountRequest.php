<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Enum\AccountLevel;
use Hampel\Saasu\Api\Enum\AccountType;
use Hampel\Saasu\Api\Support\Cast;

/**
 * An account to insert, or the changes to one.
 *
 *     AccountRequest::of('Consulting income', AccountType::Income, 'AUD')
 *
 * `IsBankAccount` IS REQUIRED ON AN INSERT, even for an account that is not one. Measured
 * 2026-09-14: without it Saasu answers 400 "IsBankAccount has to be included in request". of()
 * sends `false`; withBankAccount(true) turns it into a bank account.
 *
 * SO IS A CURRENCY, at least in a file with multi-currency on - measured the same day, 400
 * "Please include a valid currency". of() takes it rather than guessing the file's base currency;
 * pass null only for a file known not to need one.
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

    public static function of(string $name, AccountType $type, ?string $currency, AccountLevel $level = AccountLevel::Detail): self
    {
        $request = self::make()
            ->withName(Cast::required($name, 'An account needs a name.'))
            ->withAccountType($type)
            ->withAccountLevel($level)
            ->withBankAccount(false);

        return $currency === null ? $request : $request->withCurrency(strtoupper(trim($currency)));
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

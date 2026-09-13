<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

/**
 * The bank account a contact is paid into.
 */
final class DirectDepositDetailsRequest extends Payload
{
    protected const WRITABLE = [
        'AcceptDirectDeposit',
        'AccountName',
        'AccountBSB',
        'AccountNumber',
    ];

    public function withAcceptDirectDeposit(?bool $acceptDirectDeposit): static
    {
        return $this->with('AcceptDirectDeposit', $acceptDirectDeposit);
    }

    public function withAccountName(?string $accountName): static
    {
        return $this->with('AccountName', $accountName);
    }

    public function withAccountBsb(?string $accountBsb): static
    {
        return $this->with('AccountBSB', $accountBsb);
    }

    public function withAccountNumber(?string $accountNumber): static
    {
        return $this->with('AccountNumber', $accountNumber);
    }
}

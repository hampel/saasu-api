<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Enum\JournalEntryType;
use Hampel\Saasu\Api\Support\Cast;

/**
 * One line of a journal.
 *
 *     JournalItemRequest::debit(1234, 100.00)
 *     JournalItemRequest::credit(5678, 100.00)
 */
final class JournalItemRequest extends Payload
{
    protected const WRITABLE = [
        'Type',
        'AccountId',
        'Amount',
        'TaxCode',
    ];

    public static function debit(int $accountId, float $amount, ?string $taxCode = null): self
    {
        return self::make()->withType(JournalEntryType::Debit)->withAccountId(Cast::id($accountId, 'account'))->withAmount($amount)->withTaxCode($taxCode);
    }

    public static function credit(int $accountId, float $amount, ?string $taxCode = null): self
    {
        return self::make()->withType(JournalEntryType::Credit)->withAccountId(Cast::id($accountId, 'account'))->withAmount($amount)->withTaxCode($taxCode);
    }

    public function withType(JournalEntryType|string|null $type): static
    {
        return $this->with('Type', $type instanceof \BackedEnum ? $type->value : $type);
    }

    public function withAccountId(?int $accountId): static
    {
        return $this->with('AccountId', $accountId);
    }

    public function withAmount(int|float|null $amount): static
    {
        return $this->with('Amount', $amount);
    }

    public function withTaxCode(?string $taxCode): static
    {
        return $this->with('TaxCode', $taxCode);
    }
}

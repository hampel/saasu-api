<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Enum\JournalEntryType;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Support\Cast;

/**
 * A journal to insert, or the changes to one.
 *
 * imbalance() says whether the debits and credits agree before the request is sent. It is not
 * checked automatically: Saasu's rules for a journal with tax codes are not documented, and a
 * rejection this package invented could not be argued with.
 */
final class JournalRequest extends Payload
{
    protected const WRITABLE = [
        'TransactionDate',
        'Summary',
        'Reference',
        'Notes',
        'JournalContactId',
        'Currency',
        'FxRate',
        'AutoPopulateFxRate',
        'RequiresFollowUp',
        'Tags',
        'Items',
        'LastUpdatedId',
    ];

    /**
     * @param  list<JournalItemRequest>  $lines
     */
    public static function of(\DateTimeInterface|string $date, array $lines): self
    {
        if ($lines === []) {
            throw new InvalidArgumentException('A journal needs at least one line.');
        }

        return self::make()->withTransactionDate($date)->withItems($lines);
    }

    /**
     * Debits less credits, over the lines on this request. Zero when it balances.
     */
    public function imbalance(): float
    {
        $sum = 0.0;

        foreach (is_array($this->get('Items')) ? $this->get('Items') : [] as $line) {
            $line = is_array($line) ? $line : [];
            $amount = Cast::float($line['Amount'] ?? null) ?? 0.0;
            $sum += Cast::enum(JournalEntryType::class, $line['Type'] ?? null) === JournalEntryType::Credit ? -$amount : $amount;
        }

        return round($sum, 2);
    }

    public function withTransactionDate(\DateTimeInterface|string|null $transactionDate): static
    {
        return $this->putDate('TransactionDate', $transactionDate);
    }

    public function withSummary(?string $summary): static
    {
        return $this->with('Summary', $summary);
    }

    public function withReference(?string $reference): static
    {
        return $this->with('Reference', $reference);
    }

    public function withNotes(?string $notes): static
    {
        return $this->with('Notes', $notes);
    }

    public function withJournalContactId(?int $journalContactId): static
    {
        return $this->with('JournalContactId', $journalContactId);
    }

    public function withCurrency(?string $currency): static
    {
        return $this->with('Currency', $currency);
    }

    public function withFxRate(int|float|null $fxRate): static
    {
        return $this->with('FxRate', $fxRate);
    }

    public function withAutoPopulateFxRate(?bool $autoPopulateFxRate): static
    {
        return $this->with('AutoPopulateFxRate', $autoPopulateFxRate);
    }

    public function withRequiresFollowUp(?bool $requiresFollowUp): static
    {
        return $this->with('RequiresFollowUp', $requiresFollowUp);
    }

    /**
     * @param  list<string>  $tags
     */
    public function withTags(array $tags): static
    {
        return $this->putTags($tags);
    }

    /**
     * @param  list<JournalItemRequest|array<string, mixed>>  $items
     */
    public function withItems(array $items): static
    {
        return $this->putList('Items', $items);
    }

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}

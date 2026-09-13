<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Exception\InvalidArgumentException;

/**
 * A stock transfer to insert, or the changes to one. Saasu refuses one with no lines.
 */
final class ItemTransferRequest extends Payload
{
    protected const WRITABLE = [
        'Date',
        'Summary',
        'Notes',
        'RequiresFollowUp',
        'Tags',
        'Items',
        'LastUpdatedId',
    ];

    /**
     * @param  list<TransferItemRequest>  $lines
     */
    public static function of(\DateTimeInterface|string $date, array $lines): self
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Saasu refuses a stock transfer with no lines.');
        }

        return self::make()->withDate($date)->withItems($lines);
    }

    public function withDate(\DateTimeInterface|string|null $date): static
    {
        return $this->putDate('Date', $date);
    }

    public function withSummary(?string $summary): static
    {
        return $this->with('Summary', $summary);
    }

    public function withNotes(?string $notes): static
    {
        return $this->with('Notes', $notes);
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
     * @param  list<TransferItemRequest|array<string, mixed>>  $items
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

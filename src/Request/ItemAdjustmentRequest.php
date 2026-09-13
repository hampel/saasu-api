<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Exception\InvalidArgumentException;

/**
 * A stock adjustment to insert, or the changes to one. Saasu refuses one with no lines.
 */
final class ItemAdjustmentRequest extends Payload
{
    protected const WRITABLE = [
        'Date',
        'Summary',
        'Notes',
        'RequiresFollowUp',
        'Tags',
        'AdjustmentItems',
        'LastUpdatedId',
    ];

    /**
     * @param  list<AdjustmentItemRequest>  $lines
     */
    public static function of(\DateTimeInterface|string $date, array $lines): self
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Saasu refuses a stock adjustment with no lines.');
        }

        return self::make()->withDate($date)->withAdjustmentItems($lines);
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
     * @param  list<AdjustmentItemRequest|array<string, mixed>>  $adjustmentItems
     */
    public function withAdjustmentItems(array $adjustmentItems): static
    {
        return $this->putList('AdjustmentItems', $adjustmentItems);
    }

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}

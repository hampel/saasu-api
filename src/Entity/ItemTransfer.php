<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A stock transfer between items - how a combo's components become the combo, among other things.
 *
 * The list answers summaries without `Notes` or `Items`.
 */
final class ItemTransfer extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?\DateTimeImmutable $date = null,
        public readonly ?string $summary = null,
        public readonly ?string $notes = null,
        public readonly ?bool $requiresFollowUp = null,
        /** @var list<string> */
        public readonly array $tags = [],
        /** @var list<TransferItem> */
        public readonly array $items = [],
        public readonly ?\DateTimeImmutable $createdDateUtc = null,
        public readonly ?\DateTimeImmutable $lastModifiedDateUtc = null,
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
            Cast::text($row['LastUpdatedId'] ?? null),
            Cast::date($row['Date'] ?? null),
            Cast::text($row['Summary'] ?? null),
            Cast::text($row['Notes'] ?? null),
            Cast::bool($row['RequiresFollowUp'] ?? null),
            Cast::tags($row['Tags'] ?? null),
            Cast::objects($row['Items'] ?? null, TransferItem::fromArray(...)),
            Cast::datetime($row['CreatedDateUtc'] ?? null),
            Cast::datetime($row['LastModifiedDateUtc'] ?? null),
            $row,
        );
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Result;

use Hampel\Saasu\Api\Entity\ContactMatch;
use Hampel\Saasu\Api\Entity\ItemMatch;
use Hampel\Saasu\Api\Entity\TransactionMatch;
use Hampel\Saasu\Api\Support\Cast;

/**
 * What a search found, in three lists with a total each.
 *
 * UNLIKE A LIST ENDPOINT, A SEARCH HAS TOTALS - how many of each kind matched across every page -
 * so hasMore() here is arithmetic rather than a guess.
 */
final class SearchResults implements \JsonSerializable
{
    /**
     * @param  list<TransactionMatch>  $transactions
     * @param  list<ContactMatch>  $contacts
     * @param  list<ItemMatch>  $items
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly array $transactions,
        public readonly array $contacts,
        public readonly array $items,
        public readonly int $totalTransactions,
        public readonly int $totalContacts,
        public readonly int $totalItems,
        public readonly int $page,
        public readonly int $pageSize,
        public readonly array $raw = [],
    ) {
    }

    public static function fromResponse(ApiResponse $response, int $page, int $pageSize): self
    {
        $body = $response->object();
        $transactions = Cast::objects($body['Transactions'] ?? null, TransactionMatch::fromArray(...));
        $contacts = Cast::objects($body['Contacts'] ?? null, ContactMatch::fromArray(...));
        $items = Cast::objects($body['InventoryItems'] ?? null, ItemMatch::fromArray(...));

        return new self(
            $transactions,
            $contacts,
            $items,
            Cast::int($body['TotalTransactionsFound'] ?? null) ?? count($transactions),
            Cast::int($body['TotalContactsFound'] ?? null) ?? count($contacts),
            Cast::int($body['TotalInventoryItemsFound'] ?? null) ?? count($items),
            $page,
            $pageSize,
            $body,
        );
    }

    public function total(): int
    {
        return $this->totalTransactions + $this->totalContacts + $this->totalItems;
    }

    public function isEmpty(): bool
    {
        return $this->total() === 0 && $this->transactions === [] && $this->contacts === [] && $this->items === [];
    }

    /**
     * Whether a later page holds more of any kind.
     */
    public function hasMore(): bool
    {
        $seen = $this->page * $this->pageSize;

        return $seen < max($this->totalTransactions, $this->totalContacts, $this->totalItems);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Enum\SearchScope;
use Hampel\Saasu\Api\Enum\SearchTransactionType;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Result\SearchResults;

/**
 * Full-text search across transactions, contacts and items, at `Search`.
 *
 * AN INDEX, NOT THE LEDGER. What comes back is what the search platform has indexed, with fewer
 * fields than the records themselves - read a record by id before acting on it.
 */
final class Search extends Endpoint
{
    /**
     * Search.
     *
     * HIGHLIGHTING IS OFF unless asked for. Saasu's own default is on, which wraps matched terms in
     * markup inside the returned strings - a surprise in anything that is not a search page.
     */
    public function query(
        string $keywords,
        SearchScope $scope = SearchScope::All,
        ?SearchTransactionType $transactionType = null,
        bool $highlight = false,
        int $page = 1,
        ?int $pageSize = null,
    ): SearchResults {
        $keywords = trim($keywords);

        if ($keywords === '') {
            throw new InvalidArgumentException('A search needs keywords.');
        }

        if ($transactionType !== null && $scope !== SearchScope::Transactions) {
            throw new InvalidArgumentException(
                'A transaction type narrows a transaction search; Saasu applies it only with the Transactions scope.'
            );
        }

        $pageSize ??= $this->connection->config()->pageSize;
        Page::assertValidPageSize($pageSize);

        $response = $this->apiGet('Search', [
            'Keywords' => $keywords,
            'Scope' => $scope->value,
            'TransactionType' => $transactionType?->value,
            'IncludeSearchTermHighlights' => $highlight,
            'Page' => max(1, $page),
            'PageSize' => $pageSize,
        ]);

        return SearchResults::fromResponse($response, max(1, $page), $pageSize);
    }
}

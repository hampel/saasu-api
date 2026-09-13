<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A file's defaults. Marked deprecated and read-only on the file identity.
 */
final class FileSettings extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?bool $saleAmountsIncludeTax = null,
        public readonly ?bool $purchaseAmountsIncludeTax = null,
        public readonly ?TradingTerms $saleTradingTerms = null,
        public readonly ?TradingTerms $purchaseTradingTerms = null,
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
            Cast::bool($row['SaleAmountsIncludeTax'] ?? null),
            Cast::bool($row['PurchaseAmountsIncludeTax'] ?? null),
            Cast::nested($row['SaleTradingTerms'] ?? null, TradingTerms::fromArray(...)),
            Cast::nested($row['PurchaseTradingTerms'] ?? null, TradingTerms::fromArray(...)),
            $row,
        );
    }
}

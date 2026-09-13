<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * One file the current login can reach - what `FileIdentities` lists.
 *
 * `Id` is the file id to configure a client with.
 */
final class FileSummary extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $country = null,
        public readonly ?string $zone = null,
        public readonly ?string $currencyCode = null,
        public readonly ?string $subscriptionName = null,
        public readonly ?FileSettings $fileSettings = null,
        public readonly ?bool $isTaxRegistered = null,
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
            Cast::text($row['Name'] ?? null),
            Cast::text($row['Country'] ?? null),
            Cast::text($row['Zone'] ?? null),
            Cast::text($row['CurrencyCode'] ?? null),
            Cast::text($row['SubscriptionName'] ?? null),
            Cast::nested($row['FileSettings'] ?? null, FileSettings::fromArray(...)),
            Cast::bool($row['IsTaxRegistered'] ?? null),
            $row,
        );
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * The bank account a contact is paid into, when they accept direct deposit.
 */
final class DirectDepositDetails extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?bool $acceptDirectDeposit = null,
        public readonly ?string $accountName = null,
        public readonly ?string $accountBsb = null,
        public readonly ?string $accountNumber = null,
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
            Cast::bool($row['AcceptDirectDeposit'] ?? null),
            Cast::text($row['AccountName'] ?? null),
            Cast::text($row['AccountBSB'] ?? null),
            Cast::text($row['AccountNumber'] ?? null),
            $row,
        );
    }
}

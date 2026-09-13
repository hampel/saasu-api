<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * Whether a contact accepts cheques, and who to make one out to.
 */
final class ChequeDetails extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?bool $acceptCheque = null,
        public readonly ?string $chequePayableTo = null,
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
            Cast::bool($row['AcceptCheque'] ?? null),
            Cast::text($row['ChequePayableTo'] ?? null),
            $row,
        );
    }
}

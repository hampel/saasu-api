<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

/**
 * Whether a contact accepts cheques, and who to make one out to.
 */
final class ChequeDetailsRequest extends Payload
{
    protected const WRITABLE = [
        'AcceptCheque',
        'ChequePayableTo',
    ];

    public function withAcceptCheque(?bool $acceptCheque): static
    {
        return $this->with('AcceptCheque', $acceptCheque);
    }

    public function withChequePayableTo(?string $chequePayableTo): static
    {
        return $this->with('ChequePayableTo', $chequePayableTo);
    }
}

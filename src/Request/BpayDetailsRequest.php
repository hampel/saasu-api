<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

/**
 * A contact's BPAY biller code and customer reference number.
 */
final class BpayDetailsRequest extends Payload
{
    protected const WRITABLE = [
        'BillerCode',
        'CRN',
    ];

    public function withBillerCode(?string $billerCode): static
    {
        return $this->with('BillerCode', $billerCode);
    }

    public function withCrn(?string $crn): static
    {
        return $this->with('CRN', $crn);
    }
}

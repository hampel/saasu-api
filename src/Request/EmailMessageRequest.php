<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Support\Cast;

/**
 * The email Saasu sends when an invoice is written with SendEmailToContact. Saasu requires `From`
 * and `To`.
 */
final class EmailMessageRequest extends Payload
{
    protected const WRITABLE = [
        'From',
        'To',
        'Cc',
        'Bcc',
        'Subject',
        'Body',
    ];

    public static function of(string $from, string $to, ?string $subject = null, ?string $body = null): self
    {
        return self::make()
            ->withFrom(Cast::required($from, 'An invoice email needs a From address.'))
            ->withTo(Cast::required($to, 'An invoice email needs a To address.'))
            ->withSubject($subject)
            ->withBody($body);
    }

    public function withFrom(?string $from): static
    {
        return $this->with('From', $from);
    }

    public function withTo(?string $to): static
    {
        return $this->with('To', $to);
    }

    public function withCc(?string $cc): static
    {
        return $this->with('Cc', $cc);
    }

    public function withBcc(?string $bcc): static
    {
        return $this->with('Bcc', $bcc);
    }

    public function withSubject(?string $subject): static
    {
        return $this->with('Subject', $subject);
    }

    public function withBody(?string $body): static
    {
        return $this->with('Body', $body);
    }
}

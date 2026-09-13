<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

/**
 * The user has two-factor authentication on, so a token needs the code Saasu has just sent.
 *
 * ASKING FOR A TOKEN WITHOUT THE CODE SENDS AN SMS. That is the documented behaviour of
 * `authorisation/token-2fa`: the first request texts a one-time code to the user's registered
 * mobile and answers with this; the second carries the code. `codeWasSent()` says whether the
 * text went.
 *
 * An unattended integration cannot answer that, which is why one should authenticate as a user
 * without 2FA, or be seeded once with an AccessGrant obtained interactively - the refresh
 * token that comes with it lasts twelve months.
 */
final class TwoFactorRequiredException extends NotAuthenticatedException
{
    public function codeWasSent(): bool
    {
        return $this->error->twoFactorCodeSent ?? false;
    }
}

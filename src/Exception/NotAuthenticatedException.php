<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

/**
 * Saasu did not accept the credential - HTTP 401.
 *
 * TWO DIFFERENT BODIES, measured 2026-09-13. A data endpoint answers a missing, invalid or
 * expired credential with a 401 and NO BODY AT ALL, whichever scheme was used. The token
 * endpoints answer with an OAuth error object - `{"error": "invalid_credentials",
 * "error_description": "Invalid username or password", ...}` - which is on `error`.
 */
class NotAuthenticatedException extends ApiException
{
    /**
     * The OAuth error code, when the token endpoint sent one: `invalid_credentials`,
     * `invalid_client` for a refresh token that is not valid, `2fa_code_required`.
     */
    public function oauthError(): ?string
    {
        return $this->error?->oauthError;
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\User;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Request\UserRequest;
use Hampel\Saasu\Api\Support\Cast;

/**
 * The login the credential belongs to, at `User`.
 *
 * FILE-SCOPED DESPITE BEING ABOUT THE USER. Measured 2026-09-14: `GET User` without a file id
 * answers 401 "No FileId or Invalid FileId supplied.", so these send the configured file id like
 * any other endpoint. The password reset is the exception, sent without a credential or a file.
 *
 * EVERYTHING HERE BUT get() CHANGES HOW A PERSON LOGS IN. Two-factor enrolment texts their
 * phone; opting out removes a protection; a password reset emails them. None of it belongs in an
 * unattended integration, and it is wrapped for completeness rather than recommended.
 */
final class Users extends Endpoint
{
    public function get(): User
    {
        return User::fromArray($this->connection->get('User')->object());
    }

    /**
     * Update the current user's name, mobile number and timezone. Saasu requires a first name.
     */
    public function update(UserRequest $request): ?string
    {
        if (Cast::text($request->get('FirstName')) === null) {
            throw new InvalidArgumentException('Saasu requires a first name on a user update; build the request from() the user.');
        }

        return $this->connection->put('User', $request->toArray())->statusMessage();
    }

    /**
     * EMAILS A PASSWORD RESET to the user. Sent without a credential, as Saasu documents it.
     */
    public function resetPassword(string $username): ?string
    {
        return $this->connection->unauthenticated()->post('User/reset-password', [
            'Username' => Cast::required($username, 'A password reset needs the username.'),
        ])->statusMessage();
    }

    /**
     * TEXTS A ONE-TIME CODE to the mobile number, to enrol it for two-factor authentication.
     * Answers with the provider user id verifyTwoFactorOptIn() needs. Saasu accepts only
     * Australian mobile numbers.
     */
    public function optInToTwoFactor(string $mobileNumber): ?int
    {
        return Cast::int($this->connection->post('User/opt-in-to-2fa', [
            'MobileNumber' => Cast::required($mobileNumber, 'Two-factor enrolment needs a mobile number.'),
        ])->value('ProviderUserId'));
    }

    /**
     * Complete two-factor enrolment with the code that was texted.
     */
    public function verifyTwoFactorOptIn(int $providerUserId, #[\SensitiveParameter] string $code): ?string
    {
        return $this->connection->post('User/verify-2fa-opt-in', [
            'ProviderUserId' => $providerUserId,
            'Otp' => Cast::required($code, 'Verifying two-factor enrolment needs the code that was sent.'),
        ])->statusMessage();
    }

    /**
     * TURNS TWO-FACTOR AUTHENTICATION OFF for the user.
     */
    public function optOutOfTwoFactor(): ?string
    {
        return $this->connection->post('User/opt-out-from-2fa')->statusMessage();
    }
}

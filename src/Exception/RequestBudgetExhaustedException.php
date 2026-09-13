<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Exception;

/**
 * The client has made as many requests as Config::$requestBudget allows, and refused to make
 * another. Nothing was sent.
 *
 * This is a guard the caller asked for, not a failure Saasu reported. It exists because of
 * what happens without it: Saasu blocks a file's API access for 24 hours from the moment its
 * daily request limit is exceeded, for every integration using that file - so a walk over a
 * collection that turns out to be larger than expected, or a retry loop that never ends, takes
 * down things that had nothing to do with it.
 */
final class RequestBudgetExhaustedException extends SaasuException
{
    public static function after(int $budget): self
    {
        return new self(sprintf(
            'Refusing to send another request: this client has used its budget of %d. Saasu blocks '
                . 'API access to a file for 24 hours once its daily limit is exceeded, so the budget is '
                . 'stopping here rather than finding out.',
            $budget
        ));
    }
}

<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Support;

/**
 * How many requests a client and everything derived from it have sent.
 *
 * Shared by reference between a Client and the clients its with*() methods return, because
 * the thing being counted - Saasu's daily quota - belongs to the file and the login, not to one
 * PHP object. A budget that reset whenever withFileId() was called would guard nothing.
 *
 * A CHILD COUNTS FROM ZERO, for a budget on one job run by a long-lived client - see
 * `Client::withRequestBudget()`. Every request it counts is counted by its parent too, and it
 * remembers the parent's budget, so a child cannot be used to get round a budget above it.
 */
final class RequestCounter
{
    private int $count = 0;

    public function __construct(
        private readonly ?RequestCounter $parent = null,
        private readonly ?int $parentBudget = null,
    ) {
    }

    public function increment(): int
    {
        $this->parent?->increment();

        return ++$this->count;
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * A counter starting at zero whose requests are also counted here.
     *
     * @param  int|null  $budget  the budget this counter is held to, which the child stays held to
     */
    public function child(?int $budget): self
    {
        return new self($this, $budget);
    }

    /**
     * The first budget above this counter that is spent, or null when none is.
     */
    public function exhaustedParentBudget(): ?int
    {
        if ($this->parent === null) {
            return null;
        }

        if ($this->parentBudget !== null && $this->parent->count() >= $this->parentBudget) {
            return $this->parentBudget;
        }

        return $this->parent->exhaustedParentBudget();
    }
}

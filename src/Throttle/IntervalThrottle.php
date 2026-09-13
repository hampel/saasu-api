<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Throttle;

use Hampel\Saasu\Api\Exception\InvalidArgumentException;

/**
 * At most one request per interval, within this process.
 *
 * The first request goes immediately; each one after waits out whatever is left of the interval
 * since the previous one started. A client that makes a request a minute never waits at all.
 *
 * The sleep and the clock are injectable, so a test can assert on the waits without taking
 * them, and an event loop can yield instead of blocking.
 */
final class IntervalThrottle implements Throttle
{
    /**
     * Saasu's documented limit: "Maximum 1 request p/sec".
     */
    public const SAASU_INTERVAL = 1.0;

    private ?float $last = null;

    /** @var \Closure(float): void */
    private readonly \Closure $sleep;

    /** @var \Closure(): float */
    private readonly \Closure $clock;

    /**
     * @param  (callable(float): void)|null  $sleep  receives seconds; defaults to usleep()
     * @param  (callable(): float)|null  $clock  answers seconds; defaults to microtime(true)
     */
    public function __construct(
        public readonly float $interval = self::SAASU_INTERVAL,
        ?callable $sleep = null,
        ?callable $clock = null,
    ) {
        if ($interval < 0) {
            throw new InvalidArgumentException('A throttle interval cannot be negative.');
        }

        $this->sleep = $sleep !== null
            ? \Closure::fromCallable($sleep)
            : static function (float $seconds): void {
                usleep((int) round($seconds * 1_000_000));
            };

        $this->clock = $clock !== null
            ? \Closure::fromCallable($clock)
            : static fn (): float => microtime(true);
    }

    public function wait(): void
    {
        $now = ($this->clock)();

        if ($this->last !== null) {
            $remaining = $this->interval - ($now - $this->last);

            if ($remaining > 0) {
                ($this->sleep)($remaining);
                $now += $remaining;
            }
        }

        $this->last = $now;
    }
}

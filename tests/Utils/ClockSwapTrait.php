<?php

declare(strict_types=1);

namespace App\Tests\Utils;

use App\Utils\Clock;

/**
 * Replaces the container's `App\Utils\Clock` binding with a `FakeClock` so
 * WebTestCases can pin the current time without per-test wiring. Call
 * `swapClock(string $now)` in setUp; the binding lives until tearDown.
 *
 * Requires the host to extend `Symfony\Bundle\FrameworkBundle\Test\WebTestCase`.
 */
trait ClockSwapTrait
{
    private FakeClock $fakeClock;

    protected function swapClock(string $now): FakeClock
    {
        $this->fakeClock = new FakeClock($now);
        static::getContainer()->set(Clock::class, $this->fakeClock);

        return $this->fakeClock;
    }
}

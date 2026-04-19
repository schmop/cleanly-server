<?php

declare(strict_types=1);

namespace App\Tests\Utils;

use App\Utils\Clock;

final class FakeClock implements Clock
{
    public function __construct(private string $now)
    {
    }

    public function setNow(string $now): void
    {
        $this->now = $now;
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->now);
    }
}

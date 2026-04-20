<?php

namespace App\Auth;
use App\Auth\Entity\RefreshToken;
use App\Utils\Clock;
use Webmozart\Assert\Assert;

class RefreshTokenRefresher
{
    function __construct(
        private RefreshTokenTtlProvider $ttlProvider,
        private Clock $clock,
    ) {
    }

    /**
     * @throws \Webmozart\Assert\InvalidArgumentException
     */
    function refresh(RefreshToken $refreshToken): void
    {
        $interval = \DateInterval::createFromDateString(
            sprintf(
                '%d seconds',
                $this->ttlProvider->ttl
            )
        );
        Assert::isInstanceOf($interval, \DateInterval::class);
        $refreshToken->refresh(
            $this->clock->now()->add(
                $interval,
            )
        );
    }
}

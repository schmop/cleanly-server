<?php

namespace App\Auth;
use App\Auth\Entity\RefreshToken;
use App\Utils\Clock;

class RefreshTokenRefresher
{
    function __construct(
        private RefreshTokenTtlProvider $ttlProvider,
        private Clock $clock,
    ) {
    }

    /**
     * @throws \DateMalformedIntervalStringException
     */
    function refresh(RefreshToken $refreshToken): void
    {
        $interval = \DateInterval::createFromDateString(
            sprintf(
                '%d seconds',
                $this->ttlProvider->ttl
            )
        );
        $refreshToken->refresh(
            $this->clock->now()->add(
                $interval,
            )
        );
    }
}

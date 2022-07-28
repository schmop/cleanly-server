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

    function refresh(RefreshToken $refreshToken): void
    {
        $refreshToken->refresh(
            $this->clock->now()->add(
                \DateInterval::createFromDateString(sprintf(
                    '%d seconds', 
                    $this->ttlProvider->ttl
                ))
            )
        );
    }
}

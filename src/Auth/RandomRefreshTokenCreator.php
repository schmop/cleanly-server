<?php

namespace App\Auth;
use App\Auth\RefreshTokenCreator;
use App\Auth\Entity\RefreshToken;
use App\Entity\User;
use App\Utils\Random;
use App\Utils\Clock;

class RandomRefreshTokenCreator implements RefreshTokenCreator
{
    function __construct(
        private RefreshTokenTtlProvider $ttlProvider,
        private RefreshTokenRefresher $tokenRefresher,
        private Random $random,
        private Clock $clock,
        private RefreshTokenRepository $refreshTokenRepository
    ) {
    }

    function create(User $user): RefreshToken
    {
        $token = new RefreshToken(
            $this->random->getRandomString(64),
            $user,
        );
        $this->tokenRefresher->refresh($token);
        $this->refreshTokenRepository->save($token);

        return $token;
    }
}

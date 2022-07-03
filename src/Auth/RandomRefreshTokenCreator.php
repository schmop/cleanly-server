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
        private int $ttl, 
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
            $this->clock->now()->add(\DateInterval::createFromDateString(sprintf('%d seconds', $this->ttl))),
        );
        $this->refreshTokenRepository->save($token);

        return $token;
    }
}

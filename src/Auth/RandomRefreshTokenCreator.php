<?php

namespace App\Auth;
use App\Auth\RefreshTokenCreator;
use App\Auth\Entity\RefreshToken;
use App\Persistence\PersistenceException;
use App\User\Entity\User;
use App\Utils\Random;

class RandomRefreshTokenCreator implements RefreshTokenCreator
{
    function __construct(
        private RefreshTokenRefresher $tokenRefresher,
        private Random $random,
        private RefreshTokenRepository $refreshTokenRepository
    ) {
    }

    /**
     * @throws \Webmozart\Assert\InvalidArgumentException
     * @throws PersistenceException
     */
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

<?php

namespace App\Auth;

use App\Auth\Entity\RefreshToken;

interface RefreshTokenRepository
{
    public function save(RefreshToken $refreshToken): void;
    public function findByToken(string $token): ?RefreshToken;
}

<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Entity\RefreshToken;
use App\Entity\User;

interface RefreshTokenCreator
{
    public function create(User $user): RefreshToken;
}

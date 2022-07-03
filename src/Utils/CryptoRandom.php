<?php

namespace App\Utils;

final class CryptoRandom implements Random
{
    public function getRandomString(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }
}

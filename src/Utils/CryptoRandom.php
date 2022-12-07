<?php

namespace App\Utils;

final class CryptoRandom implements Random
{
    /**
     * @param int<1, max> $bytes
     */
    public function getRandomString(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }
}

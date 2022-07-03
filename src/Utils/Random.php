<?php

namespace App\Utils;

interface Random
{
    public function getRandomString(int $bytes): string;
}

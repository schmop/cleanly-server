<?php

namespace App\Utils;

interface Clock
{
    public function now(): \DateTimeImmutable;
}

<?php

namespace App\Utils;

interface Base64UrlInterface
{
    public function encode(string $str): string;

    public function decode(string $str): string;
}
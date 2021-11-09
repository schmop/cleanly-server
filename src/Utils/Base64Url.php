<?php

namespace App\Utils;

class Base64Url implements Base64UrlInterface
{
    public function encode(string $str): string
    {
        return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
    }
    public function decode(string $str): string
    {
        return base64_decode(str_pad(
            strtr($str, '-_', '+/'),
            strlen($str) % 4,
            '=',
            STR_PAD_RIGHT
        ));
    }
}
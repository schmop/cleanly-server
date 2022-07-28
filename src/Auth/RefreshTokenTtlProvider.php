<?php

namespace App\Auth;

class RefreshTokenTtlProvider
{
    function __construct(public readonly int $ttl) {
    }
}

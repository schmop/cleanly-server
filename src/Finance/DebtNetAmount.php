<?php

namespace App\Finance;

class DebtNetAmount
{
    public function __construct(public int $userId, public int $amount)
    {
    }
}
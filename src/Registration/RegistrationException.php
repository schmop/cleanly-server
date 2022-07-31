<?php

namespace App\Registration;

class RegistrationException extends \Exception
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct();
    }
}

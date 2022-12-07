<?php

namespace App\Registration;

class RegistrationException extends \Exception
{
    /**
     * @param string[] $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct();
    }
}

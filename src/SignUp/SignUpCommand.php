<?php

declare(strict_types=1);

namespace App\SignUp;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ensures validity of provided information and is used for user-creation
 */
class SignUpCommand
{
    /**
     * @Assert\NotBlank
     */
    private string $name;

    /**
     * @Assert\Email
     */
    private string $email;

    /**
     * @Assert\NotCompromisedPassword
     */
    private string $passwd;

    public function __construct($name, $email, $passwd)
    {
        $this->name = $name;
        $this->email = $email;
        $this->passwd = $passwd;
    }

    public static function fromRequest(Request $request): self
    {
        return new self(/**TODO**/);
    }
}
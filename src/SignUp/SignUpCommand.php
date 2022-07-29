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
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $name,
        #[Assert\Email]
        public readonly string $mail,
        #[Assert\NotCompromisedPassword]
        public readonly string $passwd,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $name = $request->request->get('_name');
        $mail = $request->request->get('_mail');
        $password = $request->request->get('_password');

        if (!is_string($mail) || !is_string($password)) {
            throw new \InvalidArgumentException("'_name', '_mail' and '_password' must be provided!");
        }


        return new self($name, $mail, $password);
    }
}
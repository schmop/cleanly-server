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
    private string $mail;

    /* TODO: Readd this in production
     * @Assert\NotCompromisedPassword
     */
    private string $passwd;

    public function __construct(string $name, string $mail, string $passwd)
    {
        $this->name = $name;
        $this->mail = $mail;
        $this->passwd = $passwd;
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

    public function getMail(): string
    {
        return $this->mail;
    }

    public function getPassword(): string
    {
        return $this->passwd;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
<?php

namespace App\Registration\Entity;

use App\Registration\RegistrationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RegistrationRepository::class)]
class Registration
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string')]
        public readonly string $uuid,

        #[ORM\Column(type: 'string', unique: true)]
        public readonly string $mail,

        #[ORM\Column(type: 'string')]
        public readonly string $name,

        #[ORM\Column(type: 'string')]
        public readonly string $token,

        #[ORM\Column(type: 'string')]
        public readonly string $password,

        #[ORM\Column(type: 'datetime_immutable')]
        public readonly \DateTimeImmutable $registratedAt,
    ) {
    }
}

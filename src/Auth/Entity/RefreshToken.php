<?php

namespace App\Auth\Entity;

use App\Auth\RefreshTokenRepository;
use App\Utils\Clock;
use Doctrine\ORM\Mapping as ORM;
use App\User\Entity\User;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
class RefreshToken
{
    public function __construct(
        #[ORM\Column(type: "string")]
        #[ORM\Id]
        private string $token,

        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false, onDelete: 'cascade')]
        private User $user,

        #[ORM\Column(type: "datetime_immutable", nullable: true)]
        private ?\DateTimeImmutable $validUntil = null,
    ) {
    }

    public function refresh(\DateTimeImmutable $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function isOutdated(Clock $clock): bool
    {
        if (null === $this->validUntil) {
            return true;
        }

        return $this->validUntil->getTimestamp() <= $clock->now()->getTimestamp();
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}

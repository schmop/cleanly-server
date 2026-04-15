<?php

declare(strict_types=1);

namespace App\AccountDeletion\Entity;

use App\AccountDeletion\AccountDeletionRequestRepository;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountDeletionRequestRepository::class)]
class AccountDeletionRequest
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string')]
        public readonly string $uuid,

        #[ORM\Column(type: 'string')]
        public readonly string $token,

        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        public readonly User $user,

        #[ORM\Column(type: 'datetime_immutable')]
        public readonly \DateTimeImmutable $expiresAt,
    ) {
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }
}

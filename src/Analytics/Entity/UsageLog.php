<?php

namespace App\Analytics\Entity;

use App\Analytics\ActivityType;
use App\Auth\RefreshTokenRepository;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
readonly class UsageLog
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: "string")]
        private string $uuid,
        #[ORM\Column(type: "string", nullable: false, enumType: ActivityType::class)]
        private ActivityType        $activityType,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false, onDelete: 'cascade')]
        private User                $user,
        #[ORM\Column(type: "datetime_immutable", nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
        private \DateTimeImmutable $timestamp,
    ) {
    }

    public function getActivityType(): ActivityType
    {
        return $this->activityType;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}

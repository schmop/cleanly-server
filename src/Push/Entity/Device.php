<?php

namespace App\Push\Entity;

use App\Push\DeviceRepository;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeviceRepository::class)]
class Device
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string')]
        private readonly string $id,

        #[ORM\Column(type: 'string')]
        private string $pushId,

        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false)]
        private readonly User $user,
    ) {
    }

	public function getId(): string
    {
        return $this->id;
    }

    public function getPushId(): string
    {
        return $this->pushId;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setPushId(string $pushId): void
    {
        $this->pushId = $pushId;
    }
}

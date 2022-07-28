<?php

namespace App\Push\Entity;

use App\Push\DeviceRepository;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeviceRepository::class)]
class Device
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    private $id;

    #[ORM\Column(type: 'string')]
    private $pushId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $user;

    public function __construct(string $id, string $pushId, User $user)
    {
        $this->user = $user;
        $this->id = $id;
        $this->pushId = $pushId;
    }

	function getId(): string
    {
		return $this->id;
    }

	function getPushId(): string
    {
		return $this->pushId;
	}
    
	function getUser(): User
    {
		return $this->user;
	}
}

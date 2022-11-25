<?php

namespace App\Household\Entity;

use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass:HouseholdInviteRepository::class)]
#[UniqueEntity(
    fields: ['household', 'user'],
)]
class HouseholdPrivilege implements \JsonSerializable
{
    public const PRIVILEGE_USER = 0;
    public const PRIVILEGE_MODERATOR = 1;
    public const PRIVILEGE_ADMIN = 2;

    public function __construct(
        #[ORM\ManyToOne(targetEntity:Household::class, mappedBy:"privileges")]
        #[ORM\JoinTable(name:"household", referencedColumnName: 'id')]
        public Household $household,

        #[ORM\ManyToOne(targetEntity:User::class)]
        #[ORM\JoinTable(name:"`user`", referencedColumnName: 'id')]
        public User $user,

        #[ORM\Column(type:"integer")]
        public int $level,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'household' => $this->household->getId(),
            'user' => $this->user->getId(),
            'privilege' => $this->level,
        ];
    }
}

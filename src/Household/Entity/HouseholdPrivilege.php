<?php

namespace App\Household\Entity;

use App\Household\HouseholdInviteRepository;
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

    public const PRIVILEGES = [
        self::PRIVILEGE_USER,
        self::PRIVILEGE_MODERATOR,
        self::PRIVILEGE_ADMIN,
    ];

    public function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity:Household::class, inversedBy:"privileges")]
        #[ORM\JoinColumn(name:"household", referencedColumnName: 'id')]
        public Household $household,

        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity:User::class)]
        #[ORM\JoinColumn(name:"`user`", referencedColumnName: 'id')]
        public User $user,

        #[ORM\Column(type:"integer")]
        public int $level,
    ) {
    }

    /**
     * @return array{
     *      household: int|null,
     *      user: int|null,
     *      privilege: int,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'household' => $this->household->getId(),
            'user' => $this->user->getId(),
            'privilege' => $this->level,
        ];
    }
}

<?php

namespace App\User\Entity;

use App\Registration\RegistrationRepository;
use Doctrine\ORM\Mapping as ORM;
use App\User\Entity\User;

#[ORM\Entity(repositoryClass: RegistrationRepository::class)]
class UserSettings
{
    public function __construct(
        #[ORM\Id]
        #[ORM\OneToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name:"user_id", referencedColumnName:"id")]
        public readonly User $user,

        #[ORM\Column(type: 'boolean')]
        public bool $notifyTaskDone = true,

        #[ORM\Column(type: 'boolean')]
        public bool $notifyTaskDue = true,

        #[ORM\Column(type: 'boolean')]
        public bool $notifyInvites = true,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'notifyTaskDone' => $this->notifyTaskDone,
            'notifyTaskDue' => $this->notifyTaskDue,
            'notifyInvites' => $this->notifyInvites,
        ];
    }
}

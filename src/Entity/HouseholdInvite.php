<?php

namespace App\Entity;

use App\Repository\HouseholdInviteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=HouseholdInviteRepository::class)
 */
class HouseholdInvite implements \JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    private string $token;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private \DateTimeImmutable $validUntil;

    /**
     * @ORM\ManyToOne(targetEntity="Household", inversedBy="invites")
     * @ORM\JoinColumn(name="household_id", referencedColumnName="id")
     */
    private Household $household;

    /**
     * @ORM\ManyToOne(targetEntity="Household", inversedBy="invites")
     * @ORM\JoinColumn(name="invitee_id", referencedColumnName="id")
     */
    private ?User $invitee;

    public function __construct(string $token, Household $household, ?User $invitee = null)
    {
        $this->household = $household;
        $this->token = $token;
        $this->invitee = $invitee;
        $this->validUntil = (new \DateTimeImmutable())->add(new \DateInterval('PT2H'));
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getValidUntil(): \DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getInvitee(): ?User
    {
        return $this->invitee;
    }

    public function jsonSerialize(): array
    {
        return [
            'householdId' => $this->household->getId(),
            'householdName' => $this->household->getName(),
            'inviter' => $this->household->getAdmin()->jsonSerialize(),
        ];
    }
}

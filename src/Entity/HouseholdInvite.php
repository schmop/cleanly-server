<?php

namespace App\Entity;

use App\Repository\HouseholdInviteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=HouseholdInviteRepository::class)
 */
class HouseholdInvite
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

    public function __construct(string $token, Household $household)
    {
        $this->household = $household;
        $this->token = $token;
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

}

<?php

namespace App\Entity;

use App\Repository\HouseholdRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass=HouseholdRepository::class)
 */
class Household implements \JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private int $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $picture;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $color;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="admin_id", referencedColumnName="id")
     */
    private ?UserInterface $admin;

    /**
     * @ORM\ManyToMany(targetEntity="User", inversedBy="households")
     * @ORM\JoinTable(name="household_members")
     *
     * @var Collection<UserInterface>
     */
    private Collection $members;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->color = '#233662';
    }

    public static function createFromRequest(Request $request, UserInterface $user): self
    {
        if (null === $request->request->get('name')) {
            throw new \InvalidArgumentException('No name set!');
        }
        $household = new self();
        $household->setName($request->request->get('name'));
        $household->setAdmin($user);
        $household->addMember($user);

        return $household;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPicture(): ?string
    {
        return $this->picture;
    }

    public function setPicture(?string $picture): self
    {
        $this->picture = $picture;

        return $this;
    }

    public function getAdmin(): ?UserInterface
    {
        return $this->admin;
    }

    public function setAdmin(UserInterface $admin): self
    {
        $this->admin = $admin;

        return $this;
    }

    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(UserInterface $member): self
    {
        $this->members->add($member);

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'picture' => $this->getPicture(),
            'color' => $this->getColor(),
            'members' => $this->getMembers()->map(static function(User $user) {
                return $user->jsonSerialize();
            })->toArray()
        ];
    }
}

<?php

namespace App\User\Entity;

use App\Household\Entity\Household;
use App\Household\Entity\HouseholdInvite;
use App\Household\Entity\HouseholdRank;
use App\RankSort\RankSortableList;
use App\Todo\Entity\Checklist;
use App\User\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @implements RankSortableList<HouseholdRank>
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: "`user`")]
class User implements UserInterface, PasswordAuthenticatedUserInterface, \JsonSerializable, RankSortableList
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "SEQUENCE")]
    #[ORM\Column(type: "integer")]
    private int|null $id;

    #[ORM\Column(name: "email", type: "string", length: 180, unique: true, nullable: false)]
    private string $mail;

    #[ORM\Column(type: "string", nullable: false)]
    private string $name;

    /**
     * @var string[]
     */
    #[ORM\Column(type: "json", nullable: false)]
    private array $roles = [];

    #[ORM\Column(type: "string", nullable: false)]
    private string $password;

    /** @var Collection<int, Household> */
    #[ORM\ManyToMany(targetEntity: Household::class, mappedBy: "members")]
    private Collection $households;


    /** @var Collection<int, HouseholdInvite> */
    #[ORM\OneToMany(mappedBy: "invitee", targetEntity: HouseholdInvite::class)]
    private Collection $invites;

    #[ORM\OneToOne(mappedBy: "user", targetEntity: UserSettings::class)]
    private null|UserSettings $userSettings = null;


    /** @var Collection<int, Checklist> */
    #[ORM\ManyToMany(targetEntity: Checklist::class, mappedBy: "subscribers")]
    private Collection $checklistSubscriptions;

    /** @var Collection<int, HouseholdRank> */
    #[ORM\OneToMany(mappedBy: "user", targetEntity: HouseholdRank::class)]
    #[ORM\OrderBy(["sortRank" => "ASC"])]
    private Collection $householdRanks;

    public function __construct(string $mail, string $name)
    {
        $this->mail = $mail;
        $this->name = $name;
        $this->households = new ArrayCollection();
        $this->checklistSubscriptions = new ArrayCollection();
        $this->invites = new ArrayCollection();
        $this->householdRanks = new ArrayCollection();
    }

    public function getUuid(): string
    {
        return (string)$this->id;
    }

    /**
     * @return Collection<int, HouseholdRank>
     */
    public function getHouseholdRanks(): Collection
    {
        return $this->householdRanks;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMail(): string
    {
        return $this->mail;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setMail(string $mail): self
    {
        $this->mail = $mail;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        \assert($this->mail !== '');

        return $this->mail;
    }

    /**
     * @deprecated since Symfony 5.3, use getUserIdentifier instead
     */
    public function getUsername(): string
    {
        return $this->mail;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param string[] $roles
     * @return $this
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return Household[]
     */
    public function getHouseholds(): array
    {
        return $this->households->getValues();
    }

    /**
     * @return HouseholdInvite[]
     */
    public function getInvites(): array
    {
        return $this->invites->getValues();
    }

    /**
     * @return Collection<int, Checklist>
     */
    public function getChecklistSubscriptions(): Collection
    {
        return $this->checklistSubscriptions;
    }

    /**
     * @return array{
     *      id: int|null,
     *      name: string|null,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
        ];
    }

    function getUserSettings(): UserSettings
    {
        // TODO: Service to create missing usersettings?
        return $this->userSettings ?? new UserSettings($this);
    }
}

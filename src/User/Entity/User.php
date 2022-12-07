<?php

namespace App\User\Entity;

use App\User\UserRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\User\Entity\UserSettings;
use App\Household\Entity\HouseholdInvite;
use App\Household\Entity\Household;
use Doctrine\Common\Collections\ArrayCollection;

 #[ORM\Entity(repositoryClass: UserRepository::class)]
 #[ORM\Table(name: "`user`")]
class User implements UserInterface, PasswordAuthenticatedUserInterface, \JsonSerializable
{
     #[ORM\Id]
     #[ORM\GeneratedValue]
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
    #[ORM\OneToMany(targetEntity: HouseholdInvite::class, mappedBy: "invitee")]
    private Collection $invites;

    #[ORM\OneToOne(targetEntity: UserSettings::class, mappedBy: "user")]
    private null|UserSettings $userSettings = null;

    public function __construct(string $mail, string $name)
    {
        $this->mail = $mail;
        $this->name = $name;
        $this->households = new ArrayCollection();
        $this->invites = new ArrayCollection();
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
        return (string) $this->mail;
    }

    /**
     * @deprecated since Symfony 5.3, use getUserIdentifier instead
     */
    public function getUsername(): string
    {
        return (string) $this->mail;
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
     * Returning a salt is only needed, if you are not using a modern
     * hashing algorithm (e.g. bcrypt or sodium) in your security.yaml.
     *
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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

	function getUserSettings(): UserSettings {
        // TODO: Service to create missing usersettings?
		return $this->userSettings ?? new UserSettings($this);
	}
}

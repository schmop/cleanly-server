<?php

namespace App\Household\Entity;

use App\Household\HouseholdRepository;
use App\Household\NotInHouseholdException;
use App\Household\ReassignmentStrategy;
use App\Task\Entity\Task;
use App\Todo\Entity\Checklist;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\Request;

#[ORM\Entity(repositoryClass: HouseholdRepository::class)]
class Household implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "SEQUENCE")]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $webhookUrl;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $webhookSecret;

    #[ORM\Column(type: "string", nullable: false, enumType: ReassignmentStrategy::class, options: ['default' => 'none'])]
    private ReassignmentStrategy $reassignmentStrategy;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: "households")]
    #[ORM\JoinTable(name: "household_members")]
    private Collection $members;

    /** @var Collection<int, HouseholdPrivilege> */
    #[ORM\OneToMany(targetEntity: HouseholdPrivilege::class, mappedBy: "household", cascade: ["all"], orphanRemoval: true)]
    private Collection $privileges;

    /** @var Collection<int, HouseholdInvite> */
    #[ORM\OneToMany(targetEntity: HouseholdInvite::class, mappedBy: "household")]
    private Collection $invites;

    /** @var Collection<int, Task> */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: "household")]
    private Collection $tasks;

    /** @var Collection<int, Checklist> */
    #[ORM\OneToMany(targetEntity: Checklist::class, mappedBy: "household", cascade: ["all"], orphanRemoval: true)]
    #[ORM\OrderBy(["sortRank" => "ASC"])]
    private Collection $checklists;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->invites = new ArrayCollection();
        $this->tasks = new ArrayCollection();
        $this->privileges = new ArrayCollection();
        $this->checklists = new ArrayCollection();
    }

    public static function createFromRequest(Request $request, User $user): self
    {
        $name = $request->request->get('name');
        if (!is_string($name)) {
            throw new \InvalidArgumentException('No name set!');
        }
        $household = new self();
        $household->setName($name);
        $household->setUserPrivilege($user, HouseholdPrivilege::PRIVILEGE_ADMIN);
        $household->setReassignmentStrategy(ReassignmentStrategy::None);
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

    public function getWebhookSecret(): ?string
    {
        return $this->webhookSecret;
    }

    public function setWebhookSecret(?string $webhookSecret): self
    {
        $this->webhookSecret = $webhookSecret;

        return $this;
    }

    public function getWebhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(?string $webhookUrl): self
    {
        $this->webhookUrl = $webhookUrl;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $member): self
    {
        $this->members->add($member);

        return $this;
    }

    public function removeMember(User $member): self
    {
        $this->members->removeElement($member);
        foreach ($this->privileges as $privilege) {
            if ($privilege->user === $member) {
                $this->privileges->removeElement($privilege);
                break;
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, HouseholdInvite>
     */
    public function getInvites(): Collection
    {
        return $this->invites;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    /**
     * @return Collection<int, Checklist>
     */
    public function getChecklists(): Collection
    {
        return $this->checklists;
    }

    /**
     * @return Collection<int, HouseholdPrivilege>
     */
    public function getPrivileges(): Collection
    {
        return $this->privileges;
    }

    public function setUserPrivilege(User $user, int $level): void
    {
        if (!in_array($level, HouseholdPrivilege::PRIVILEGES)) {
            throw new \InvalidArgumentException('Invalid household privilege given!');
        }
        foreach ($this->privileges as $privilege) {
            if ($privilege->user === $user) {
                $privilege->level = $level;

                return;
            }
        }

        $this->privileges->add(new HouseholdPrivilege($this, $user, $level));
    }

    /**
     * @throws NotInHouseholdException
     */
    public function getUserPrivilege(User $user): int
    {
        if (!$this->members->contains($user)) {
            throw new NotInHouseholdException();
        }
        foreach ($this->privileges as $privilege) {
            if ($privilege->user === $user) {
                return $privilege->level;
            }
        }

        return HouseholdPrivilege::PRIVILEGE_USER;
    }

    public function getReassignmentStrategy(): ReassignmentStrategy
    {
        return $this->reassignmentStrategy;
    }

    public function setReassignmentStrategy(ReassignmentStrategy $reassignmentStrategy): void
    {
        $this->reassignmentStrategy = $reassignmentStrategy;
    }

    /**
     * @return array{
     *     id: int|null,
     *     name: string|null,
     *     reassignmentStrategy: ReassignmentStrategy,
     *     webhookUrl: string|null,
     *     users: array<int, mixed>,
     *     tasks: array<int, mixed>,
     *     checklist: array<int, mixed>,
     *     privileges: array<int, mixed>,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'reassignmentStrategy' => $this->getReassignmentStrategy(),
            'webhookUrl' => $this->getWebhookUrl(),
            'users' => $this->getMembers()->map(
                static fn(User $user) => $user->jsonSerialize()
            )->toArray(),
            'tasks' => $this->getTasks()->map(
                static fn(Task $task) => $task->jsonSerialize()
            )->toArray(),
            'checklists' => $this->getChecklists()->map(
                static fn(Checklist $checklist) => $checklist->jsonSerialize()
            )->toArray(),
            'privileges' => $this->getPrivileges()->map(
                static fn(HouseholdPrivilege $privilege) => $privilege->jsonSerialize()
            )->toArray(),
        ];
    }

}

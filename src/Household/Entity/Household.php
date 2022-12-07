<?php

namespace App\Household\Entity;

use App\Household\HouseholdRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\Request;
use App\Todo\Entity\Todo;
use App\Task\Entity\Task;
use App\User\Entity\User;

 #[ORM\Entity(repositoryClass:HouseholdRepository::class)]
class Household implements \JsonSerializable
{
     #[ORM\Id]
     #[ORM\GeneratedValue]
     #[ORM\Column(type:"integer")]
    private int $id;

     #[ORM\Column(type:"string", length:255)]
    private string $name;

     #[ORM\Column(type:"string", length:255, nullable:true)]
    private ?string $picture;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity:User::class, inversedBy:"households")]
    #[ORM\JoinTable(name:"household_members")]
    private Collection $members;

    /** @var Collection<int, HouseholdPrivilege> */
    #[ORM\OneToMany(targetEntity:HouseholdPrivilege::class, mappedBy:"household", cascade:["all"], orphanRemoval: true)]
    private Collection $privileges;

    /** @var Collection<int, HouseholdInvite> */
    #[ORM\OneToMany(targetEntity:HouseholdInvite::class, mappedBy:"household")]
    private Collection $invites;

    /** @var Collection<int, Task> */
    #[ORM\OneToMany(targetEntity:Task::class, mappedBy:"household")]
    private Collection $tasks;

    /** @var Collection<int, Todo> */
    #[ORM\OneToMany(targetEntity:Todo::class, mappedBy:"household", cascade:["all"], orphanRemoval:true)]
    private Collection $checklist;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->invites = new ArrayCollection();
        $this->tasks = new ArrayCollection();
        $this->privileges = new ArrayCollection();
        $this->checklist = new ArrayCollection();
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
     * @return Collection<int, Todo>
     */
    public function getChecklist(): Collection
    {
        return $this->checklist;
    }

    /**
     * @return Collection<int, Todo>
     */
    public function getSortedChecklist(): Collection
    {
        $checklist = $this->checklist->toArray();
        $sortedChecklist = [];
        $nextUuid = null;
        while (!empty($checklist)) {
            foreach ($checklist as $index => $todo) {
                if ($todo->getNext() === $nextUuid) {
                    array_unshift($sortedChecklist, $todo);
                    array_splice($checklist, $index, 1);
                    $nextUuid = $todo->getUuid();
                    continue 2;
                }
            }
            throw new \LogicException('Checklist is not sortable, the chain is broken');
        }

        return new ArrayCollection($sortedChecklist);
    }

    /**
     * @return Collection<int, HouseholdPrivilege>
     */
	public function getPrivileges(): Collection {
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

    public function getUserPrivilege(User $user): int
    {
        foreach ($this->privileges as $privilege) {
            if ($privilege->user === $user) {
                return $privilege->level;
            }
        }

        return HouseholdPrivilege::PRIVILEGE_USER;
    }

    /**
     * @return array{
     *     id: int|null,
     *     name: string|null,
     *     picture: string|null,
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
            'picture' => $this->getPicture(),
            'users' => $this->getMembers()->map(
                static fn (User $user) => $user->jsonSerialize()
            )->toArray(),
            'tasks' => $this->getTasks()->map(
                static fn (Task $task) => $task->jsonSerialize()
            )->toArray(),
            'checklist' => $this->getSortedChecklist()->map(
                static fn (Todo $todo) => $todo->jsonSerialize()
            )->toArray(),
            'privileges' => $this->getPrivileges()->map(
                static fn (HouseholdPrivilege $privilege) => $privilege->jsonSerialize()
            )->toArray(),
        ];
    }
}

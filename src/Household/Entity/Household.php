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

     #[ORM\Column(type:"string", length:255)]
    private string $color;

     #[ORM\ManyToOne(targetEntity:User::class)]
     #[ORM\JoinColumn(name:"admin_id", referencedColumnName:"id")]
    private ?User $admin;

    /** @var Collection<User> */
     #[ORM\ManyToMany(targetEntity:User::class, inversedBy:"households")]
     #[ORM\JoinTable(name:"household_members")]
    private Collection $members;

    /** @var Collection<HouseholdInvite> */
     #[ORM\OneToMany(targetEntity:HouseholdInvite::class, mappedBy:"household")]
    private Collection $invites;

    /** @var Collection<Task> */
     #[ORM\OneToMany(targetEntity:Task::class, mappedBy:"household")]
    private Collection $tasks;

    /** @var Collection<Todo> */
     #[ORM\OneToMany(targetEntity:Todo::class, mappedBy:"household", cascade:["all"], orphanRemoval:true)]
    private Collection $checklist;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->invites = new ArrayCollection();
        $this->tasks = new ArrayCollection();
        $this->color = '#233662';
    }

    public static function createFromRequest(Request $request, User $user): self
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

    public function getAdmin(): ?User
    {
        return $this->admin;
    }

    public function setAdmin(User $admin): self
    {
        $this->admin = $admin;

        return $this;
    }

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

    public function getInvites(): Collection
    {
        return $this->invites;
    }

    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function getChecklist(): Collection
    {
        return $this->checklist;
    }

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

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'picture' => $this->getPicture(),
            'color' => $this->getColor(),
            'users' => $this->getMembers()->map(static function (User $user) {
                return $user->jsonSerialize();
            })->toArray(),
            'tasks' => $this->getTasks()->map(static function (Task $task) {
                return $task->jsonSerialize();
            })->toArray(),
            'admin' => $this->getAdmin()->getId(),
            'checklist' => $this->getSortedChecklist()->map(static function (Todo $todo) {
                return $todo->jsonSerialize();
            })->toArray(),
        ];
    }
}

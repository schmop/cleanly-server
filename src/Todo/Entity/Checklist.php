<?php

namespace App\Todo\Entity;

use App\Household\Entity\Household;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Checklist implements \JsonSerializable
{
    /** @var Collection<int, Todo> */
    #[ORM\OneToMany(targetEntity: Todo::class, mappedBy: "checklist", cascade: ["all"], orphanRemoval: true)]
    private Collection $checklist;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: "checklistSubscriptions")]
    #[ORM\JoinTable(name: "checklist_subscriptions")]
    #[ORM\InverseJoinColumn(name: "user_id", referencedColumnName: "id")]
    #[ORM\JoinColumn(name: "checklist_uuid", referencedColumnName: "uuid")]
    private Collection $subscribers;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: "string")]
        private readonly string    $uuid,

        #[ORM\Column(type: "string", nullable: false)]
        private string             $name,

        #[ORM\ManyToOne(targetEntity: Household::class, inversedBy: "checklist")]
        #[ORM\JoinColumn(name: "household_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
        private readonly Household $household,

        #[ORM\Column(type: "datetime_immutable", nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
        private \DateTimeImmutable $lastUpdatedAt,

        #[ORM\Column(
            type: "string",
            nullable: false,
            options: ['default' => '0'],
        )]
        private string $sortRank,
    ) {
        $this->checklist = new ArrayCollection();
        $this->subscribers = new ArrayCollection();
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
     * @return Collection<int, Todo>
     */
    public function getChecklist(): Collection
    {
        return $this->checklist;
    }

    /**
     * @return Collection<int, User>
     */
    public function getSubscribers(): Collection
    {
        return $this->subscribers;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getLastUpdatedAt(): \DateTimeImmutable
    {
        return $this->lastUpdatedAt;
    }

    public function setLastUpdatedAt(\DateTimeImmutable $lastUpdatedAt): void
    {
        $this->lastUpdatedAt = $lastUpdatedAt;
    }

    public function getSortRank(): string
    {
        return $this->sortRank;
    }

    public function setSortRank(string $sortRank): void
    {
        $this->sortRank = $sortRank;
    }

    /**
     * @return array{
     *     uuid: string,
     *     name: string,
     *     checklist: array<int, array{
     *          uuid: string,
     *          content: string,
     *     }>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'uuid' => $this->getUuid(),
            'name' => $this->getName(),
            'rank' => $this->getSortRank(),
            'checklist' => $this->getSortedChecklist()
                ->map(static fn (Todo $todo) => $todo->jsonSerialize())
                ->toArray(),
        ];
    }
}

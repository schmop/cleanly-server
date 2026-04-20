<?php

namespace App\Todo\Entity;

use App\Household\Entity\Household;
use App\RankSort\RankSortableItem;
use App\RankSort\RankSortableList;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @implements RankSortableList<Todo>
 */
#[ORM\Entity]
class Checklist implements \JsonSerializable, RankSortableItem, RankSortableList
{
    /** @var Collection<int, Todo> */
    #[ORM\OneToMany(targetEntity: Todo::class, mappedBy: "checklist", cascade: ["all"], orphanRemoval: true)]
    #[ORM\OrderBy(["sortRank" => "ASC"])]
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

    /**
     * @return non-empty-string
     * @throws \LogicException
     */
    public function getSortRank(): string
    {
        if ('' === $this->sortRank) {
            throw new \LogicException('Sort rank must not be empty. The administrator must rebalance the checklist sorting.');
        }
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
     * @throws \LogicException
     */
    public function jsonSerialize(): array
    {
        return [
            'uuid' => $this->getUuid(),
            'name' => $this->getName(),
            'rank' => $this->getSortRank(),
            'checklist' => $this->getChecklist()
                ->map(static fn (Todo $todo) => $todo->jsonSerialize())
                ->toArray(),
        ];
    }

    public function getList(): RankSortableList
    {
        /** @phpstan-ignore-next-line */
        return $this->household;
    }
}

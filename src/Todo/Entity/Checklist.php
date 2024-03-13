<?php

namespace App\Todo\Entity;

use App\Household\Entity\Household;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Checklist implements \JsonSerializable
{
    /** @var Collection<int, Todo> */
    #[ORM\OneToMany(mappedBy: "checklist", targetEntity: Todo::class, cascade: ["all"], orphanRemoval: true)]
    private Collection $checklist;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: "string")]
        private readonly string    $uuid,

        #[ORM\Column(type: "string", nullable: false)]
        private string             $name,

        #[ORM\ManyToOne(targetEntity: Household::class, inversedBy: "checklist")]
        #[ORM\JoinColumn(name: "household_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
        private readonly Household $household,
    ) {
        $this->checklist = new ArrayCollection();
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
            'checklist' => $this->getSortedChecklist()
                ->map(static fn (Todo $todo) => $todo->jsonSerialize())
                ->toArray(),
        ];
    }
}

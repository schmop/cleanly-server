<?php

namespace App\Todo\Entity;

use AlexCrawford\LexoRank\Rank;
use App\RankSort\RankSortableItem;
use App\RankSort\RankSortableList;
use App\Todo\TodoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TodoRepository::class)]
class Todo implements \JsonSerializable, RankSortableItem
{
    #[ORM\Id]
    #[ORM\Column(type: "string")]
    private string $uuid;

    #[ORM\Column(type: "string", nullable: false)]
    private string $content;

    #[ORM\Column(
        type: "string",
        nullable: false,
        options: ['default' => '0'],
    )]
    private string $sortRank;

    #[ORM\ManyToOne(targetEntity: Checklist::class, inversedBy: "checklist")]
    #[ORM\JoinColumn(name: "checklist_uuid", referencedColumnName: "uuid", nullable: false, onDelete: "CASCADE")]
    private Checklist $checklist;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $checkedAt;

    public function __construct(string $uuid, string $content, Checklist $checklist, null|string $sortRank = null)
    {
        $this->uuid = $uuid;
        $this->content = $content;
        $this->checklist = $checklist;
        $this->sortRank = $sortRank ?? Rank::forEmptySequence()->get();
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getChecklist(): Checklist
    {
        return $this->checklist;
    }

    /**
     * @return array{
     *      uuid: string,
     *      content: string,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'uuid' => $this->getUuid(),
            'content' => $this->getContent(),
            'checked_at' => $this->checkedAt?->getTimestamp(),
        ];
    }

    public function getSortRank(): string
    {
        if ('' === $this->sortRank) {
            return Rank::forEmptySequence()->get();
        }
        return $this->sortRank;
    }

    public function getList(): RankSortableList
    {
        // PHPStan does not understand that Checklist implements RankSortableList<Todo>
        /** @phpstan-ignore-next-line */
        return $this->checklist;
    }

    public function setSortRank(string $sortRank): void
    {
        $this->sortRank = $sortRank;
    }

    public function setCheckedAt(\DateTimeImmutable|null $timestamp): void
    {
        $this->checkedAt = $timestamp;
    }
}

<?php

namespace App\Todo\Entity;

use App\Todo\TodoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TodoRepository::class)]
class Todo implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\Column(type: "string")]
    private string $uuid;

    #[ORM\Column(type: "string", nullable: false)]
    private string $content;

    #[ORM\Column(type: "string", unique: true, nullable: true)]
    private ?string $nextUuid = null;

    #[ORM\ManyToOne(targetEntity: Checklist::class, inversedBy: "checklist")]
    #[ORM\JoinColumn(name: "checklist_uuid", referencedColumnName: "uuid", nullable: false, onDelete: "CASCADE")]
    private Checklist $checklist;

    public function __construct(string $uuid, string $content, Checklist $checklist)
    {
        $this->uuid = $uuid;
        $this->content = $content;
        $this->checklist = $checklist;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function setNext(?string $nextUuid): void
    {
        $this->nextUuid = $nextUuid;
    }

    public function getNext(): ?string
    {
        return $this->nextUuid;
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
        ];
    }
}

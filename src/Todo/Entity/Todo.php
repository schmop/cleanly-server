<?php

namespace App\Todo\Entity;

use App\Todo\TodoRepository;
use App\Household\Entity\Household;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TodoRepository::class)]
class Todo implements \JsonSerializable
{
     #[ORM\Id]
     #[ORM\Column(type: "string")]
    private string $uuid;

    #[ORM\Column(type: "string")]
    private string $content;

    #[ORM\Column(type: "string", nullable: true, unique: true)]
    private ?string $nextUuid = null;

     #[ORM\ManyToOne(targetEntity: Household::class, inversedBy: "checklist")]
     #[ORM\JoinColumn(name: "household_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private Household $household;

    public function __construct(string $uuid, string $content, Household $household)
    {
        $this->uuid = $uuid;
        $this->content = $content;
        $this->household = $household;
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

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function jsonSerialize(): array
    {
        return [
            'uuid' => $this->getUuid(),
            'content' => $this->getContent(),
        ];
    }
}

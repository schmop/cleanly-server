<?php

namespace App\Todo\Entity;

use App\Todo\TodoRepository;
use App\Entity\Household;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TodoRepository::class)]
class Todo implements \JsonSerializable
{
     #[ORM\Id]
     #[ORM\Column(type: "string")]
    private string $uuid;

    #[ORM\Column(type: "string")]
    private string $content;

    #[ORM\Column(type: "integer")]
    private int $weight;

     #[ORM\ManyToOne(targetEntity: Household::class, inversedBy: "checklist")]
     #[ORM\JoinColumn(name: "household_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private Household $household;

    public function __construct(string $uuid, string $content, int $weight, Household $household)
    {
        $this->uuid = $uuid;
        $this->content = $content;
        $this->weight = $weight;
        $this->household = $household;
    }

    public static function createFromData(array $data, int $weight, Household $household): self
    {
        return new self($data['uuid'], $data['content'], $weight, $household);
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function setWeight(int $weight): void
    {
        $this->weight = $weight;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getWeight(): int
    {
        return $this->weight;
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

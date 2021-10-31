<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\Request;

/**
 * @ORM\Entity(repositoryClass=TaskRepository::class)
 */
class Task
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $lastCompleted;

    /**
     * @ORM\Column(type="integer")
     */
    private $duration;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=510, nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $icon;

    public static function createFromRequest(Request $request): self
    {
        $task = new self();
        $task->name = $request->request->get('name');
        $task->duration = (int) $request->request->get('duration');
        if (null !== $description = $request->request->get('description')) {
            $task->description = $description;
        }
        if (null !== $icon = $request->request->get('icon')) {
            $task->icon = $icon;
        }

        return $task;
    }

    public function serialize(): string
    {
        return json_encode($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLastCompleted(): ?\DateTimeImmutable
    {
        return $this->lastCompleted;
    }

    public function setLastCompleted(?\DateTimeImmutable $lastCompleted): self
    {
        $this->lastCompleted = $lastCompleted;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;

        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }
}

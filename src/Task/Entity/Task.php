<?php

namespace App\Task\Entity;

use App\Task\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use App\Household\Entity\Household;
use Doctrine\Common\Collections\Collection;


#[ORM\Entity(repositoryClass: TaskRepository::class)]

class Task implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $lastCompleted = null;

    #[ORM\Column(type: "datetime_immutable", options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $lastNotifiedAt;

    #[ORM\Column(type: "integer")]
    private int $duration;

    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    #[ORM\Column(type: "string", length: 510, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(type: "integer", options: ['default' => 0])]
    private int $stars = 0;

    #[ORM\ManyToOne(targetEntity: Household::class, inversedBy: "tasks")]
    #[ORM\JoinColumn(name: "household_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private Household $household;

    /**
     * @var Collection<int, TaskLog>
     */
    #[ORM\OneToMany(targetEntity: TaskLog::class, mappedBy: "task")]
    private Collection $logs;

    public function __construct()
    {
        $this->logs = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function setHousehold(Household $household): void
    {
        $this->household = $household;
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

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
    }

    public function getLastNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->lastNotifiedAt;
    }

    public function setLastNotifiedAt(\DateTimeImmutable $lastNotifiedAt): void
    {
        $this->lastNotifiedAt = $lastNotifiedAt;
    }

    /**
     * @return array{
     *      id: int,
     *      name: string|null,
     *      icon: string|null,
     *      description: string|null,
     *      lastComplete: int|null,
     *      duration: int|null,
     *      stars: int,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'icon' => $this->getIcon(),
            'description' => $this->getDescription(),
            'lastComplete' => $this->getLastCompleted()?->getTimestamp(),
            'duration' => $this->getDuration(),
            'stars' => $this->getStars(),
        ];
    }

    /**
     * @return Collection<int, TaskLog>
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(TaskLog $taskLog): void
    {
        $this->logs->add($taskLog);
    }

    public function getStars(): int
    {
        return $this->stars;
    }

    public function setStars(int $stars): void
    {
        $this->stars = $stars;
    }
}

<?php

namespace App\Task\Entity;

use App\Household\Entity\Household;
use App\Task\TaskRepository;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: TaskRepository::class)]
class Task implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "SEQUENCE")]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $lastCompleted = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $lastPushedAt = null;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $duration;

    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    #[ORM\Column(type: "string", length: 510, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $hue = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(type: "integer", options: ['default' => 0])]
    private int $stars = 0;

    #[ORM\OneToOne(mappedBy: 'task', targetEntity: ReminderConfig::class, cascade: ['persist'], orphanRemoval: true)]
    private ?ReminderConfig $reminderConfig = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'assignee_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $assignee = null;

    #[ORM\ManyToOne(targetEntity: Household::class, inversedBy: "tasks")]
    #[ORM\JoinColumn(name: "household_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private Household $household;

    /**
     * @var Collection<int, TaskLog>
     */
    #[ORM\OneToMany(mappedBy: "task", targetEntity: TaskLog::class)]
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

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function assignTo(?User $assignee): void
    {
        $this->assignee = $assignee;
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

    public function setDuration(?int $duration): self
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

    public function getHue(): ?int
    {
        return $this->hue;
    }

    public function setHue(?int $hue): void
    {
        $this->hue = $hue;
    }

    public function getLastPushedAt(): ?\DateTimeImmutable
    {
        return $this->lastPushedAt;
    }

    public function setLastPushedAt(?\DateTimeImmutable $lastPushedAt): void
    {
        $this->lastPushedAt = $lastPushedAt;
    }

    /**
     * @return array{
     *     id: int,
     *     name: string|null,
     *     icon: string|null,
     *     hue: int|null,
     *     assignee: int|null,
     *     description: string|null,
     *     lastComplete: int|null,
     *     duration: int|null,
     *     stars: int,
     *     reminder: ReminderConfig|null,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'icon' => $this->getIcon(),
            'hue' => $this->getHue(),
            'assignee' => $this->getAssignee()?->getId(),
            'description' => $this->getDescription(),
            'lastComplete' => $this->getLastCompleted()?->getTimestamp(),
            'duration' => $this->getDuration(),
            'stars' => $this->getStars(),
            'reminder' => $this->getReminderConfig(),
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

    public function getReminderConfig(): ?ReminderConfig
    {
        return $this->reminderConfig;
    }

    public function setReminderConfig(?ReminderConfig $config): void
    {
        if ($config === null) {
            // orphanRemoval will delete the existing row on flush.
            $this->reminderConfig = null;
            return;
        }
        if ($this->reminderConfig === null) {
            $config->setTask($this);
            $this->reminderConfig = $config;
            return;
        }
        // Replace in place so the UNIQUE task_id isn't violated by
        // INSERT-before-DELETE on flush.
        $this->reminderConfig->overwriteWith($config);
    }
}

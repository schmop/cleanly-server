<?php

namespace App\Task\Entity;

use App\Task\TaskRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Household;
use Doctrine\Common\Collections\Collection;


#[ORM\Entity(repositoryClass: TaskRepository::class)]

class Task implements \JsonSerializable
{
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private int $id;

    
    #[ORM\Column(type:"datetime_immutable", nullable:true)]
    private ?\DateTimeImmutable $lastCompleted;

    #[ORM\Column(type:"integer")]
    private int $duration;

    #[ORM\Column(type:"string", length:255)]
    private string $name;

    #[ORM\Column(type:"string", length:510, nullable:true)]
    private ?string $description;

    #[ORM\Column(type:"string", length:255, nullable:true)]
    private ?string $icon;

    #[ORM\ManyToOne(targetEntity:Household::class, inversedBy:"tasks")]
    #[ORM\JoinColumn(name:"household_id", referencedColumnName:"id", onDelete:"CASCADE")]
    private Household $household;

    #[ORM\OneToMany(targetEntity:TaskLog::class, mappedBy:"task")]
    /**
     * @var Collection<TaskLog>
     */
    private Collection $logs;

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

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'icon' => $this->getIcon(),
            'description' => $this->getDescription(),
            'lastComplete' => $this->getLastCompleted()?->getTimestamp(),
            'duration' => $this->getDuration(),
        ];
    }

	/**
	 * @return Collection<TaskLog>
	 */
	public function getLogs(): Collection {
		return $this->logs;
	}

    public function addLog(TaskLog $taskLog): void {
        $this->logs->add($taskLog);
    }
}

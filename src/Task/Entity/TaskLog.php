<?php

namespace App\Task\Entity;

use App\Task\TaskLogRepository;
use Doctrine\ORM\Mapping as ORM;
use App\User\Entity\User;


#[ORM\Entity(repositoryClass: TaskLogRepository::class)]

class TaskLog implements \JsonSerializable
{

    #[ORM\Id]
    #[ORM\Column(type: "string")]
    private string $uuid;

    #[ORM\Column(type: "datetime_immutable", nullable: false)]
    private \DateTimeImmutable $timestamp;

    #[ORM\ManyToOne(targetEntity: Task::class, inversedBy: "logs")]
    #[ORM\JoinColumn(name: "task_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private Task $task;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private User $user;

    #[ORM\Column(type: "integer", nullable: false)]
    private int $stars;

    public function __construct(string $uuid, \DateTimeImmutable $timestamp, User $user, Task $task)
    {
        $this->uuid = $uuid;
        $this->user = $user;
        $this->timestamp = $timestamp;
        $this->task = $task;
        $this->stars = $task->getStars();
    }

    /**
     * @return array{
     *      uuid: string,
     *      timestamp: int,
     *      user: int|null,
     *      task: int|null,
     *      stars: int,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'uuid' => $this->uuid,
            'timestamp' => $this->timestamp->getTimestamp(),
            'user' => $this->user->getId(),
            'task' => $this->task->getId(),
            'stars' => $this->stars,
        ];
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}

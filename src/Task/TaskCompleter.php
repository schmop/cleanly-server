<?php

namespace App\Task;

use App\Task\Entity\Task;
use App\User\Entity\User;
use App\Utils\Clock;

class TaskCompleter
{
    private const RATE_LIMIT = 5 * 60; // 5 minutes

    public function __construct(
        private readonly TaskRepository    $taskRepository,
        private readonly TaskLogFactory    $taskLogFactory,
        private readonly TaskLogRepository $taskLogRepository,
        private readonly Clock             $clock,
    ) {
    }

    public function markAsComplete(Task $task, User $user): bool
    {
        $lastTaskLogOfUserAndTask = $this->taskLogRepository->findLastByTaskAndUser($task, $user);
        $lastCompleted = $lastTaskLogOfUserAndTask?->getTimestamp()?->getTimestamp();
        if (null !== $lastCompleted && ($this->clock->now()->getTimestamp() - $lastCompleted) < self::RATE_LIMIT) {
            return false;
        }

        $task->setLastCompleted(new \DateTimeImmutable());
        $taskLog = $this->taskLogFactory->createTaskLog($user, $task);
        $this->taskRepository->save($task);
        $task->addLog($taskLog);
        $this->taskLogRepository->save($taskLog);

        return true;
    }
}

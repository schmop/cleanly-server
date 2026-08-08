<?php

namespace App\Task;

use App\Persistence\PersistenceException;
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

    /**
     * @throws PersistenceException
     */
    public function markAsComplete(Task $task, User $user, ?\DateTimeImmutable $customTimestamp = null, ?User $asUser = null): bool
    {
        $completingUser = $asUser ?? $user;
        $completionTime = $customTimestamp ?? $this->clock->now();

        // Only apply rate limiting for real-time completions (not retroactive logging)
        if ($customTimestamp === null) {
            $lastTaskLogOfUserAndTask = $this->taskLogRepository->findLastByTaskAndUser($task, $completingUser);
            $lastCompleted = $lastTaskLogOfUserAndTask?->getTimestamp()?->getTimestamp();
            if (null !== $lastCompleted && ($this->clock->now()->getTimestamp() - $lastCompleted) < self::RATE_LIMIT) {
                return false;
            }
        }

        // Only update lastCompleted if this completion is more recent
        $current = $task->getLastCompleted();
        if ($current === null || $completionTime > $current) {
            $task->setLastCompleted($completionTime);
        }

        $taskLog = $this->taskLogFactory->createTaskLog($completingUser, $task, $customTimestamp);
        $this->taskRepository->save($task);
        $task->addLog($taskLog);
        $this->taskLogRepository->save($taskLog);

        return true;
    }
}

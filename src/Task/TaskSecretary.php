<?php

namespace App\Task;

use App\Utils\Clock;
use App\Task\Entity\Task;

class TaskSecretary
{
    public function __construct(
        private readonly Clock $clock,
        private readonly TaskRepository $taskRepository,
    ) {
    }

    public function isTaskDue(Task $task): bool
    {
        $lastCompleted = $task->getLastCompleted();
        if (null === $lastCompleted || null === $task->getDuration()) {
            return false;
        }
        $dueDate = $lastCompleted->add(
            new \DateInterval(sprintf('PT%dH', $task->getDuration()))
        );

        return $dueDate < $this->clock->now();
    }

    public function wasAlreadyNotified(Task $task): bool
    {
        $lastCompleted = $task->getLastCompleted();
        if (null === $lastCompleted) {
            return true;
        }
        return $lastCompleted < $task->getLastNotifiedAt();
    }

    public function markTaskAsNotified(Task $task): void
    {
        $task->setLastNotifiedAt($this->clock->now());
        $this->taskRepository->save($task);
    }
}

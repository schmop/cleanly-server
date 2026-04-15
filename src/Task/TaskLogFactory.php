<?php

namespace App\Task;

use App\Task\Entity\Task;
use App\User\Entity\User;
use App\Task\Entity\TaskLog;
use App\Utils\Clock;
use App\Utils\UuidGenerator;

class TaskLogFactory
{

    public function __construct(
        private UuidGenerator $uuidGenerator,
        private Clock $clock,
    ) {
    }

    public function createTaskLog(User $user, Task $task, ?\DateTimeImmutable $timestamp = null): TaskLog
    {
        return new TaskLog(
            $this->uuidGenerator->v4(),
            $timestamp ?? $this->clock->now(),
            $user,
            $task
        );
    }
}
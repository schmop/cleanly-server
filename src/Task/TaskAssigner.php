<?php

namespace App\Task;

use App\Push\Pusher;
use App\Task\Entity\Task;
use App\Task\Exception\TaskAssignException;
use App\User\Entity\User;

readonly class TaskAssigner
{
    public function __construct(
        private Pusher        $pusher,
        private TaskPublisher $publisher,
    ) {
    }

    /**
     * @throws TaskAssignException
     */
    public function assignTo(Task $task, ?User $assignee): void
    {
        if (null !== $assignee && !$task->getHousehold()->getMembers()->contains($assignee)) {
            throw new TaskAssignException('Assignee is not member of household!');
        }
        $task->assignTo($assignee);
        $this->publisher->publish($task->getHousehold());
        if (null !== $assignee) {
            $this->pusher->publishTaskAssign($task, $assignee);
        }
    }
}

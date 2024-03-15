<?php

namespace App\Task;

use App\Household\ReassignmentStrategy;
use App\Push\Pusher;
use App\Task\Entity\Task;
use App\Task\Exception\TaskAssignException;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

readonly class TaskAssigner
{
    public function __construct(
        private Pusher                 $pusher,
        private TaskPublisher          $publisher,
        private TaskLogRepository      $taskLogRepository,
        private EntityManagerInterface $entityManager,
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
        $this->entityManager->flush();
    }

    /**
     * @throws TaskAssignException
     */
    public function autoAssign(Task $task, User $activeUser): void
    {
        // Only reassign if the correct person marked it as complete
        if ($activeUser !== $task->getAssignee()) {
            return;
        }
        $household = $task->getHousehold();
        switch ($household->getReassignmentStrategy()) {
            case ReassignmentStrategy::None:
                break;
            case ReassignmentStrategy::Unassign:
                $this->assignTo($task, null);
                break;
            case ReassignmentStrategy::Rotate:
                $this->assignTo(
                    $task,
                    $this->taskLogRepository->getNextAssignmentRotation($task),
                );
                break;
        }
    }
}

<?php

namespace App\Task;
use App\Hub\Publisher;
use App\Entity\Household;
use App\Task\Entity\Task;

class TaskPublisher
{
    public function __construct(private Publisher $publisher)
    {
    }

    public function publish(Household $household): void
    {
        $this->publisher->publish(
            $household->getMembers()->toArray(),
            'tasks',
            [
                'household_id' => $household->getId(),
                'tasks' => $household->getTasks()
                    ->map(fn(Task $task) => $task->jsonSerialize())
                    ->toArray()
            ]
            );
    }
}

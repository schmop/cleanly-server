<?php

namespace App\Task;

use App\Task\Entity\Task;
use App\User\Entity\User;
use App\Household\HouseholdRepository;
use App\Utils\Clock;
use Symfony\Component\HttpFoundation\Request;

final class TaskFactory
{
    public function __construct(
        private HouseholdRepository $householdRepository,
        private Clock $clock,
    ) {
    }

    public function createTaskFromRequest(Request $request, User $user): Task
    {
        $task = new Task();
        $task->setName($request->request->get('name'));
        $task->setDuration((int) $request->request->get('duration'));
        $task->setLastNotifiedAt($this->clock->now());
        if (null !== $description = $request->request->get('description')) {
            $task->setDescription($description);
        }
        if (null !== $icon = $request->request->get('icon')) {
            $task->setIcon($icon);
        }
        if (null == ($household = $this->householdRepository->find($request->request->get('household_id')))) {
            throw new \InvalidArgumentException('Task must be linked to household!');
        }
        if ($household->getAdmin() !== $user) {
            throw new \InvalidArgumentException('You are not the admin of the household');
        }
        $task->setHousehold($household);

        return $task;
    }

}
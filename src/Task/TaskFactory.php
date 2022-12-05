<?php

namespace App\Task;

use App\Household\Entity\HouseholdPrivilege;
use App\Task\Entity\Task;
use App\User\Entity\User;
use App\Household\HouseholdRepository;
use App\Household\HouseholdVoter;
use App\Utils\Clock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class TaskFactory
{
    public function __construct(
        private HouseholdRepository $householdRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
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
        if (null !== $stars = $request->request->get('stars')) {
            $task->setStars((int)$stars);
        }
        if (null == ($household = $this->householdRepository->find($request->request->get('household_id')))) {
            throw new \InvalidArgumentException('Task must be linked to household!');
        }
        if (!$this->authorizationChecker->isGranted(HouseholdVoter::MANAGE_TASKS, $household)) {
            throw new \InvalidArgumentException('Not enough privileges to create task in this household!');
        }
        $task->setHousehold($household);

        return $task;
    }

}
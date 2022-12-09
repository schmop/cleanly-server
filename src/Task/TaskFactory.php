<?php

namespace App\Task;

use App\Task\Entity\Task;
use App\Household\HouseholdRepository;
use App\Household\HouseholdVoter;
use App\Json\Json;
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

    public function createTaskFromRequest(Request $request): Task
    {
        $json = Json::fromRequest($request);
        $task = new Task();
        $task->setName($json->string('name'));
        $task->setDuration($json->tryInt('duration'));
        $task->setLastNotifiedAt($this->clock->now());
        if (null !== $description = $json->tryString('description')) {
            $task->setDescription($description);
        }
        if (null !== $icon = $json->tryString('icon')) {
            $task->setIcon($icon);
        }
        if (null !== $stars = $json->tryInt('stars')) {
            $task->setStars((int)$stars);
        }
        if (null == ($household = $this->householdRepository->find($json->int('household_id')))) {
            throw new \InvalidArgumentException('Task must be linked to household!');
        }
        if (!$this->authorizationChecker->isGranted(HouseholdVoter::MANAGE_TASKS, $household)) {
            throw new \InvalidArgumentException('Not enough privileges to create task in this household!');
        }
        $task->setHousehold($household);

        return $task;
    }

}
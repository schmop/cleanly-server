<?php

namespace App\Task;

use App\Household\HouseholdRepository;
use App\Household\HouseholdVoter;
use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use App\Task\Entity\ReminderConfig;
use App\Task\Entity\Task;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class TaskFactory
{
    public function __construct(
        private HouseholdRepository           $householdRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * @throws UnexpectedJsonException
     * @throws \InvalidArgumentException
     * @throws AccessDeniedException
     */
    public function createTaskFromRequest(Request $request): Task
    {
        $json = Json::fromRequest($request);
        if (null === ($household = $this->householdRepository->find($json->int('household_id')))) {
            throw new \InvalidArgumentException('Task must be linked to household!');
        }
        if (!$this->authorizationChecker->isGranted(HouseholdVoter::MANAGE_TASKS, $household)) {
            throw new AccessDeniedException('Not enough privileges to create task in this household!');
        }
        $task = new Task();
        $task->setName($json->string('name'));
        $task->setDuration($json->tryInt('duration'));
        $task->setDescription($json->tryString('description'));
        $task->setIcon($json->tryString('icon'));
        $task->setHue($json->tryInt('hue'));
        $task->setStars($json->tryInt('stars') ?? 0);
        $task->setHousehold($household);

        $reminderJson = $json->tryJson('reminder');
        if ($reminderJson !== null) {
            $task->setReminderConfig(ReminderConfig::fromJson($reminderJson));
        }

        return $task;
    }

}

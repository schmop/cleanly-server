<?php

declare(strict_types=1);

namespace App\Controller;

use App\Household\Entity\Household;
use App\Household\HouseholdVoter;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Json\Json;
use App\Push\Pusher;
use App\Task\Entity\Task;
use App\Task\Exception\TaskAssignException;
use App\Task\TaskAssigner;
use App\Task\TaskCompleter;
use App\Task\TaskFactory;
use App\Task\TaskLogRepository;
use App\Task\TaskPublisher;
use App\Task\TaskRepository;
use App\User\UserRepository;
use App\Webhook\WebhookNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TaskController extends UserAwareController
{
    #[Route(path: '/api/task/create', name: 'task_create', methods: ['POST'])]
    public function createTask(
        Request        $request,
        TaskFactory    $taskFactory,
        TaskRepository $taskRepository,
        TaskPublisher  $taskPublisher,
    ): JsonResponse {
        $task = $taskFactory->createTaskFromRequest($request);

        $taskRepository->save($task);
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create([]);
    }

    #[Route(path: '/api/task/edit/{id}', name: 'task_edit', methods: ['POST'])]
    public function editTask(Task $task, Request $request, TaskRepository $taskRepository, TaskPublisher $taskPublisher): JsonResponse
    {
        $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_TASKS, $task->getHousehold());

        $data = Json::fromRequest($request);
        $task->setName($data->string('name'));
        $task->setIcon($data->string('icon'));
        $task->setHue($data->tryInt('hue'));
        $task->setDuration($data->tryInt('duration'));
        $task->setStars($data->int('stars'));
        $taskRepository->save($task);
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create([]);
    }

    #[Route(path: '/api/task/mark-done/{id}', name: 'task_mark_done', methods: ['POST'])]
    public function markTaskDone(
        Task            $task,
        TaskPublisher   $taskPublisher,
        Pusher          $pusher,
        TaskCompleter   $taskCompleter,
        WebhookNotifier $webhookNotifier,
        TaskAssigner    $taskAssigner,
        LoggerInterface $logger,
    ): JsonResponse {
        $user = $this->getUser();
        $this->denyAccessUnlessGranted(HouseholdVoter::READ_HOUSEHOLD_CONTENTS, $task->getHousehold());
        if (!$taskCompleter->markAsComplete($task, $user)) {
            return JsonErrorResponse::create([
                'reason' => 'Completed twice too soon',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }
        $taskPublisher->publish($task->getHousehold());
        $pusher->publishTaskDone($task, $user);
        $webhookNotifier->notify($task, $user);
        try {
            $taskAssigner->autoAssign($task, $user);
        } catch (TaskAssignException $e) {
            $logger->error('Could not auto reassign task!', ['exception' => $e]);
        }

        return JsonSuccessResponse::create([
            'timestamp' => $task->getLastCompleted()?->getTimestamp(),
            'assignee' => $task->getAssignee(),
        ]);
    }

    #[Route(path: '/api/task/log/{id}/{fromId}', name: 'task_log', defaults: ['fromId' => null], methods: ['GET'])]
    public function fetchTaskLog(
        Household         $household,
        ?string           $fromId,
        TaskLogRepository $taskLogRepository,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(HouseholdVoter::READ_HOUSEHOLD_CONTENTS, $household);

        $logs = $taskLogRepository->findByHouseholdPaginated($household, $fromId);
        $upToId = (end($logs) ?: null)?->getUuid();

        return JsonSuccessResponse::create(['logs' => $logs, 'upToId' => $upToId]);
    }

    #[Route(path: '/api/task/stats/{id}', name: 'task_stats', methods: ['GET'])]
    public function fetchStats(
        Household         $household,
        TaskLogRepository $taskLogRepository,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(HouseholdVoter::READ_HOUSEHOLD_CONTENTS, $household);


        return JsonSuccessResponse::create([
            'durations' => $taskLogRepository->getDurationStats($household),
            'userParticipations' => $taskLogRepository->getUserParticipations($household),
        ]);
    }

    #[Route(path: '/api/task/assign/{id}', name: 'task_assign', methods: ['POST'])]
    public function assignTask(
        Task                   $task,
        Request                $request,
        UserRepository         $userRepository,
        EntityManagerInterface $entityManager,
        TaskPublisher          $taskPublisher,
        TaskAssigner           $taskAssigner,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(HouseholdVoter::READ_HOUSEHOLD_CONTENTS, $task->getHousehold());

        $data = Json::fromRequest($request);
        $assigneeId = $data->tryInt('assignee');
        $assignee = $assigneeId ? $userRepository->find($assigneeId) : null;
        try {
            $taskAssigner->assignTo($task, $assignee);
        } catch (TaskAssignException) {
            return JsonErrorResponse::create([
                'reason' => 'Cannot assign task to users, that are not members of this household!',
            ]);
        }
        $entityManager->flush();
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create([]);
    }

    #[Route(path: '/api/task/{id}', name: 'task_delete', methods: ['DELETE'])]
    public function deleteTask(Task $task, TaskRepository $taskRepository, TaskPublisher $taskPublisher): JsonResponse
    {
        $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_TASKS, $task->getHousehold());

        $taskRepository->remove($task);
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create([]);
    }
}

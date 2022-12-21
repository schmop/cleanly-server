<?php

declare(strict_types=1);

namespace App\Controller;

use App\Household\HouseholdVoter;
use App\Task\Entity\Task;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Task\TaskRepository;
use App\Task\TaskFactory;
use App\Task\TaskPublisher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Household\Entity\Household;
use App\Json\Json;
use App\Push\Pusher;
use App\Task\TaskCompleter;
use App\Task\TaskLogRepository;
use App\Webhook\WebhookNotifier;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends UserAwareController
{
    #[Route(path: '/api/task/create', name: 'task_create', methods: ['POST'])]
    public function createTask(
        Request $request,
        TaskFactory $taskFactory,
        TaskRepository $taskRepository,
        TaskPublisher $taskPublisher,
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
        $task->setDuration($data->tryInt('duration'));
        $task->setStars($data->int('stars'));
        $taskRepository->save($task);
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create([]);
    }

    #[Route(path: '/api/task/mark-done/{id}', name: 'task_mark_done', methods: ['POST'])]
    public function markTaskDone(
        Task $task,
        TaskPublisher $taskPublisher,
        Pusher $pusher,
        TaskCompleter $taskCompleter,
        WebhookNotifier $webhookNotifier,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$task->getHousehold()->getMembers()->contains($user)) {
            return JsonErrorResponse::create([
                'status' => 'error',
                'reason' => 'You are not a member of this household!'
            ]);
        }
        if (!$taskCompleter->markAsComplete($task, $user)) {
            return JsonErrorResponse::create([
                'reason' => 'Completed twice too soon',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }
        $taskPublisher->publish($task->getHousehold());
        $pusher->publishTaskDone(
            $task->getHousehold(),
            $user,
            sprintf('%s wurde erledigt!', $task->getName()),
            sprintf(
                '%s hat in %s gerade %s erledigt!',
                $user->getName(),
                $task->getHousehold()->getName(),
                $task->getName()
            ),
        );
        $webhookNotifier->notify($task, $user);

        return JsonSuccessResponse::create(['timestamp' => $task->getLastCompleted()?->getTimestamp()]);
    }

    #[Route(path: '/api/task/log/{id}/{fromId}', defaults: ['fromId' => null], name: 'task_log', methods: ['GET'])]
    public function fetchTaskLog(
        Household $household,
        ?string $fromId,
        TaskLogRepository $taskLogRepository,
    ): JsonResponse {
        if (!$household->getMembers()->contains($this->getUser())) {
            return JsonErrorResponse::create([
                'status' => 'error',
                'reason' => 'You are not a member of this household!'
            ]);
        }
        $logs = $taskLogRepository->findByHouseholdPaginated($household, $fromId);
        $upToId = (end($logs) ?: null)?->getUuid();

        return JsonSuccessResponse::create(['logs' => $logs, 'upToId' => $upToId]);
    }

    #[Route(path: '/api/task/stats/{id}', name: 'task_stats', methods: ['GET'])]
    public function fetchStats(
        Household $household,
        TaskLogRepository $taskLogRepository,
    ): JsonResponse {
        if (!$household->getMembers()->contains($this->getUser())) {
            return JsonErrorResponse::create([
                'status' => 'error',
                'reason' => 'You are not a member of this household!'
            ]);
        }

        return JsonSuccessResponse::create([
            'durations' => $taskLogRepository->getDurationStats($household),
            'userParticipations' => $taskLogRepository->getUserParticipations($household),
        ]);
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

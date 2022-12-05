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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Household\Entity\Household;
use App\Household\HouseholdRepository;
use App\Push\Pusher;
use App\Task\TaskCompleter;
use App\Task\TaskLogRepository;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends AbstractController
{
    #[Route(path: '/api/task/create', name: 'task_create', methods: ['POST'])]
    public function createTask(
        Request $request,
        TaskFactory $taskFactory,
        TaskRepository $taskRepository,
        TaskPublisher $taskPublisher,
    ): JsonResponse {
        $task = $taskFactory->createTaskFromRequest($request, $this->getUser());

        $taskRepository->save($task);
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create(['status' => 'success']);
    }

    #[Route(path: '/api/task/edit/{id}', name: 'task_edit', methods: ['POST'])]
    public function editTask(Task $task, Request $request, TaskRepository $taskRepository, TaskPublisher $taskPublisher): JsonResponse
    {
        $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_TASKS, $task->getHousehold());

        $task->setName($request->request->get('name'));
        $task->setDuration((int) $request->request->get('duration'));
        $task->setIcon($request->request->get('icon'));
        $task->setStars((int)$request->request->get('stars'));
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
            sprintf('%s wurde erledigt!', $task->getName()),
            sprintf(
                '%s hat in %s gerade %s erledigt!',
                $user->getName(),
                $task->getHousehold()->getName(),
                $task->getName()
            ),
        );

        return JsonSuccessResponse::create(['status' => 'success', 'timestamp' => $task->getLastCompleted()?->getTimestamp()]);
    }

    #[Route(path: '/api/task/log/{id}', name: 'task_log', methods: ['GET'])]
    public function fetchTaskLog(
        Household $household,
        TaskLogRepository $taskLogRepository,
    ): JsonResponse {
        if (!$household->getMembers()->contains($this->getUser())) {
            return JsonErrorResponse::create([
                'status' => 'error',
                'reason' => 'You are not a member of this household!'
            ]);
        }

        $taskLogs = $taskLogRepository->findByHousehold($household);

        return JsonSuccessResponse::create(['status' => 'success', 'logs' => $taskLogs]);
    }

    #[Route(path: '/api/task/{id}', name: 'task_delete', methods: ['DELETE'])]
    public function deleteTask(Task $task, TaskRepository $taskRepository, TaskPublisher $taskPublisher): JsonResponse
    {
        $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_TASKS, $task->getHousehold());

        $taskRepository->remove($task);
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create(['status' => 'success']);
    }
}

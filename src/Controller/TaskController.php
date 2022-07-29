<?php

declare(strict_types=1);

namespace App\Controller;

use App\Task\Entity\Task;
use App\Entity\User;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Task\TaskRepository;
use App\Task\TaskFactory;
use App\Task\TaskPublisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Household;
use App\Push\Pusher;
use App\Task\TaskCompleter;
use App\Task\TaskLogRepository;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends AbstractController
{
    /**
     * @Route("/api/task/create", "task_create", methods={"POST"})
     */
    public function createTask(
        Request $request, 
        TaskFactory $taskFactory, 
        TaskRepository $taskRepository,
        TaskPublisher $taskPublisher,
    ): JsonResponse {
        /**
         * @var User $user
         */
        $user = $this->getUser();

        $task = $taskFactory->createTaskFromRequest($request, $user);
        $taskRepository->save($task);
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create(['status' => 'success']);
    }

    /**
     * @Route("/api/task/edit/{id}", "task_edit", methods={"POST"})
     */
    public function editTask(Task $task, Request $request, TaskRepository $taskRepository, TaskPublisher $taskPublisher): JsonResponse
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();

        if ($task?->getHousehold()?->getAdmin() !== $user) {
            return JsonErrorResponse::create([
                'status' => 'error',
                'reason' => 'You do not have sufficient privileges to edit this task!'
            ]);
        }
        $task->setName($request->request->get('name'));
        $task->setDuration((int) $request->request->get('duration'));
        $task->setIcon($request->request->get('icon'));
        $taskRepository->save($task);
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create(['status' => 'success']);
    }

    /**
     * @Route("/api/task/mark-done/{id}", "task_mark_done", methods={"POST"})
     */
    public function markTaskDone(
        Task $task,
        TaskPublisher $taskPublisher,
        Pusher $pusher,
        TaskCompleter $taskCompleter,
    ): JsonResponse {
        /**
         * @var User $user
         */
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
        $pusher->publishInHousehold(
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

    /**
     * @Route("/api/task/log/{id}", "task_log", methods={"GET"})
     */
    public function fetchTaskLog(
        Household $household, 
        TaskLogRepository $taskLogRepository,
    ): JsonResponse {
        /**
         * @var User $user
         */
        $user = $this->getUser();
        if (!$household->getMembers()->contains($user)) {
            return JsonErrorResponse::create([
                'status' => 'error',
                'reason' => 'You are not a member of this household!'
            ]);
        }

        $taskLogs = $taskLogRepository->findByHousehold($household);

        return JsonSuccessResponse::create(['status' => 'success', 'logs' => $taskLogs]);
    }

    /**
     * @Route("/api/task/{id}", "task_delete", methods={"DELETE"})
     */
    public function deleteTask(Task $task, TaskRepository $taskRepository, TaskPublisher $taskPublisher): JsonResponse
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();
        if ($task->getHousehold()->getAdmin() !== $user) {
            return JsonErrorResponse::create([
                'status' => 'error',
                'reason' => 'You do not have sufficient privileges to remove this task!'
            ]);
        }

        $taskRepository->remove($task);
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create(['status' => 'success']);
    }
}

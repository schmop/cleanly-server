<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Repository\TaskRepository;
use App\Task\TaskFactory;
use App\Task\TaskPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TaskController extends AbstractController
{
    /**
     * @Route("/api/task/create", "task_create", methods={"POST"})
     */
    public function createTask(
        Request $request, 
        TaskFactory $taskFactory, 
        EntityManagerInterface $entityManager,
        TaskPublisher $taskPublisher,
    ): JsonResponse {
        /**
         * @var User $user
         */
        $user = $this->getUser();

        $task = $taskFactory->createTaskFromRequest($request, $user);
        $entityManager->persist($task);
        $entityManager->flush();
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create(['status' => 'success']);
    }

    /**
     * @Route("/api/task/edit/{id}", "task_edit", methods={"POST"})
     */
    public function editTask(Task $task, Request $request, EntityManagerInterface $entityManager, TaskPublisher $taskPublisher): JsonResponse
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
        $entityManager->flush();
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create(['status' => 'success']);
    }

    /**
     * @Route("/api/task/mark-done/{id}", "task_mark_done", methods={"POST"})
     */
    public function markTaskDone(Task $task, EntityManagerInterface $entityManager, TaskPublisher $taskPublisher): JsonResponse
    {
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

        $task->setLastCompleted(new \DateTimeImmutable());
        $entityManager->flush();
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create(['status' => 'success', 'timestamp' => $task->getLastCompleted()?->getTimestamp()]);
    }

    /**
     * @Route("/api/task/{id}", "task_delete", methods={"DELETE"})
     */
    public function deleteTask(Task $task, EntityManagerInterface $entityManager, TaskPublisher $taskPublisher): JsonResponse
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

        $entityManager->remove($task);
        $entityManager->flush();
        $taskPublisher->publish($task->getHousehold());

        return JsonSuccessResponse::create(['status' => 'success']);
    }
}

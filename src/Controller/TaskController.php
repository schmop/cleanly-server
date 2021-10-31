<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Task;
use App\HttpFoundation\JsonSuccessResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TaskController
{
    /**
     * @Route("/api/task/create", "create_task")
     */
    public function createTask(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $task = Task::createFromRequest($request);
        $entityManager->persist($task);
        $entityManager->flush();

        return JsonSuccessResponse::create(['status' => 'success', 'task' => $task->serialize()]);
    }
}
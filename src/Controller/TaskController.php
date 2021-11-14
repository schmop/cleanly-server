<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\HttpFoundation\JsonSuccessResponse;
use App\Task\TaskFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TaskController extends AbstractController
{
    /**
     * @Route("/api/task/create", "create_task")
     */
    public function createTask(Request $request, TaskFactory $taskFactory, EntityManagerInterface $entityManager): JsonResponse
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();

        $task = $taskFactory->createTaskFromRequest($request, $user);
        $entityManager->persist($task);
        $entityManager->flush();

        return JsonSuccessResponse::create(['status' => 'success']);
    }
}
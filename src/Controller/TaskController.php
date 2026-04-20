<?php

declare(strict_types=1);

namespace App\Controller;

use App\Household\Entity\Household;
use App\Household\HouseholdVoter;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use App\Persistence\PersistenceException;
use App\Push\Pusher;
use App\Task\Entity\Task;
use App\Task\Entity\TaskLog;
use App\Task\Exception\TaskAssignException;
use App\Task\Entity\ReminderConfig;
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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class TaskController extends UserAwareController
{
    #[Route(path: '/api/task/create', name: 'task_create', methods: ['POST'])]
    public function createTask(
        Request         $request,
        TaskFactory     $taskFactory,
        TaskRepository  $taskRepository,
        TaskPublisher   $taskPublisher,
        LoggerInterface $logger,
    ): JsonResponse {
        try {
            $task = $taskFactory->createTaskFromRequest($request);

            $taskRepository->save($task);
            $taskPublisher->publish($task->getHousehold());

            return JsonSuccessResponse::create([]);
        } catch (UnexpectedJsonException | PersistenceException | \InvalidArgumentException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to create task');
        }
    }

    #[Route(path: '/api/task/edit/{id}', name: 'task_edit', methods: ['POST'])]
    public function editTask(
        Task            $task,
        Request         $request,
        TaskRepository  $taskRepository,
        TaskPublisher   $taskPublisher,
        LoggerInterface $logger,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_TASKS, $task->getHousehold());

            $data = Json::fromRequest($request);
            $task->setName($data->string('name'));
            $task->setIcon($data->string('icon'));
            $task->setHue($data->tryInt('hue'));
            $task->setDuration($data->tryInt('duration'));
            $task->setStars($data->int('stars'));

            $reminderJson = $data->tryJson('reminder');
            $task->setReminderConfig(
                $reminderJson !== null ? ReminderConfig::fromJson($reminderJson) : null
            );

            $taskRepository->save($task);
            $taskPublisher->publish($task->getHousehold());

            return JsonSuccessResponse::create([]);
        } catch (AccessDeniedException | UnexpectedJsonException | PersistenceException | \InvalidArgumentException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to edit task');
        }
    }

    #[Route(path: '/api/task/mark-done/{id}', name: 'task_mark_done', methods: ['POST'])]
    public function markTaskDone(
        Task            $task,
        Request         $request,
        TaskPublisher   $taskPublisher,
        Pusher          $pusher,
        TaskCompleter   $taskCompleter,
        WebhookNotifier $webhookNotifier,
        TaskAssigner    $taskAssigner,
        UserRepository  $userRepository,
        LoggerInterface $logger,
    ): JsonResponse {
        try {
            $user = $this->getUser();
            $this->denyAccessUnlessGranted(HouseholdVoter::READ_HOUSEHOLD_CONTENTS, $task->getHousehold());

            $customTimestamp = null;
            $asUser = null;

            $content = $request->getContent();
            if ($content !== '') {
                $data = Json::fromRequest($request);

                $timestampInt = $data->tryInt('timestamp');
                if ($timestampInt !== null) {
                    $customTimestamp = (new \DateTimeImmutable())->setTimestamp($timestampInt);
                }

                $userId = $data->tryInt('userId');
                if ($userId !== null && $userId !== $user->getId()) {
                    $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_TASKS, $task->getHousehold());
                    $asUser = $userRepository->find($userId);
                    if ($asUser === null || !$task->getHousehold()->getMembers()->contains($asUser)) {
                        return JsonErrorResponse::create(['reason' => 'User is not a member of this household.'], Response::HTTP_BAD_REQUEST);
                    }
                }
            }

            if (!$taskCompleter->markAsComplete($task, $user, $customTimestamp, $asUser)) {
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
        } catch (AccessDeniedException | UnexpectedJsonException | PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to mark task as done');
        }
    }

    #[Route(path: '/api/task/log/{id}/{fromId}', name: 'task_log', defaults: ['fromId' => null], methods: ['GET'])]
    public function fetchTaskLog(
        Household         $household,
        ?string           $fromId,
        TaskLogRepository $taskLogRepository,
        LoggerInterface   $logger,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted(HouseholdVoter::READ_HOUSEHOLD_CONTENTS, $household);

            $logs = $taskLogRepository->findByHouseholdPaginated($household, $fromId);
            $upToId = (end($logs) ?: null)?->getUuid();

            return JsonSuccessResponse::create(['logs' => $logs, 'upToId' => $upToId]);
        } catch (AccessDeniedException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to fetch task log');
        }
    }

    #[Route(path: '/api/task/stats/{id}', name: 'task_stats', methods: ['GET'])]
    public function fetchStats(
        Household         $household,
        TaskLogRepository $taskLogRepository,
        LoggerInterface   $logger,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted(HouseholdVoter::READ_HOUSEHOLD_CONTENTS, $household);


            return JsonSuccessResponse::create([
                'durations' => $taskLogRepository->getDurationStats($household),
                'userParticipations' => $taskLogRepository->getUserParticipations($household),
            ]);
        } catch (AccessDeniedException | \Webmozart\Assert\InvalidArgumentException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to fetch task stats');
        }
    }

    #[Route(path: '/api/task/assign/{id}', name: 'task_assign', methods: ['POST'])]
    public function assignTask(
        Task                   $task,
        Request                $request,
        UserRepository         $userRepository,
        EntityManagerInterface $entityManager,
        TaskPublisher          $taskPublisher,
        TaskAssigner           $taskAssigner,
        LoggerInterface        $logger,
    ): JsonResponse {
        try {
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
            PersistenceException::flush($entityManager);
            $taskPublisher->publish($task->getHousehold());

            return JsonSuccessResponse::create([]);
        } catch (AccessDeniedException | UnexpectedJsonException | PersistenceException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to assign task');
        }
    }

    #[Route(path: '/api/task/{id}', name: 'task_delete', methods: ['DELETE'])]
    public function deleteTask(
        Task            $task,
        TaskRepository  $taskRepository,
        TaskPublisher   $taskPublisher,
        LoggerInterface $logger,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted(HouseholdVoter::MANAGE_TASKS, $task->getHousehold());

            $taskRepository->remove($task);
            $taskPublisher->publish($task->getHousehold());

            return JsonSuccessResponse::create([]);
        } catch (AccessDeniedException | PersistenceException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to delete task');
        }
    }

    #[Route(path: '/api/task/log/{uuid}', name: 'task_log_delete', methods: ['DELETE'])]
    public function deleteTaskLog(
        TaskLog           $taskLog,
        TaskLogRepository $taskLogRepository,
        TaskRepository    $taskRepository,
        TaskPublisher     $taskPublisher,
        LoggerInterface   $logger,
    ): JsonResponse {
        try {
            $task = $taskLog->getTask();
            $household = $task->getHousehold();
            $this->denyAccessUnlessGranted(HouseholdVoter::READ_HOUSEHOLD_CONTENTS, $household);

            $user = $this->getUser();
            $isOwner = $taskLog->getUser()->getId() === $user->getId();
            $canManage = $this->isGranted(HouseholdVoter::MANAGE_TASKS, $household);

            if (!$isOwner && !$canManage) {
                return JsonErrorResponse::create(['reason' => 'Not authorized to delete this log entry.'], Response::HTTP_FORBIDDEN);
            }

            $withinWindow = $taskLog->getTimestamp() > new \DateTimeImmutable('-24 hours');
            if (!$withinWindow) {
                return JsonErrorResponse::create(['reason' => 'Log entry is older than 24 hours.'], Response::HTTP_FORBIDDEN);
            }

            $taskLogRepository->remove($taskLog);

            $newLastLog = $taskLogRepository->findLastByTask($task);
            $task->setLastCompleted($newLastLog?->getTimestamp());
            $taskRepository->save($task);
            $taskPublisher->publish($household);

            return JsonSuccessResponse::create([]);
        } catch (AccessDeniedException | PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to delete task log');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Household\Entity\Household;
use App\Household\HouseholdRepository;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use App\Statistics\Model\StarStatisticsRequestContent;
use App\Task\TaskLogRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class StatisticsController extends UserAwareController
{
    #[Route(path: '/api/statistics/stars/v1/', name: 'statistics_stars_v1', methods: ['POST'])]
    public function fetchStarStatistics(
        Request $request,
        LoggerInterface $logger,
        HouseholdRepository $householdRepository,
    ): JsonResponse
    {
        try {
            $requestContent = StarStatisticsRequestContent::createFromJson(Json::fromRequest($request));
        } catch (UnexpectedJsonException $e) {
            $logger->error('Error! Could not retrieve star statistics. :(', ['Exception' => $e]);
            return JsonErrorResponse::create(['reason' => $e->getMessage()]);
        }

        $household = $householdRepository->findById($requestContent->householdId);

        if (null === $household || !$household->getMembers()->contains($this->getUser())) {
            return JsonErrorResponse::create([
                'reason' => 'You are not a member of this household!'
            ]);
        }

        return JsonSuccessResponse::create(['foo' => 'bar']);
    }

    #[Route(path: '/api/task/stats/{id}', name: 'task_stats', methods: ['GET'])]
    public function fetchTaskStatistics(
        Household         $household,
        TaskLogRepository $taskLogRepository,
    ): JsonResponse {
        if (!$household->getMembers()->contains($this->getUser())) {
            return JsonErrorResponse::create([
                'reason' => 'You are not a member of this household!'
            ]);
        }

        return JsonSuccessResponse::create([
            'durations' => $taskLogRepository->getDurationStats($household),
            'userParticipations' => $taskLogRepository->getUserParticipations($household),
        ]);
    }
}
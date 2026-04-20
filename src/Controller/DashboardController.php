<?php

declare(strict_types=1);

namespace App\Controller;

use App\Analytics\ActivityType;
use App\Analytics\UsageTracker;
use App\Household\Entity\Household;
use App\Household\Entity\HouseholdInvite;
use App\Household\Entity\HouseholdRank;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Persistence\PersistenceException;
use App\Todo\Entity\Checklist;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/dashboard')]
class DashboardController extends UserAwareController
{
    public function __invoke(UsageTracker $tracker, LoggerInterface $logger): Response
    {
        try {
            $user = $this->getUser();
            $tracker->track($user, ActivityType::AppOpen);

            $orderedHouseholds = array_map(
                static fn (HouseholdRank $rank) => $rank->household,
                $user->getHouseholdRanks()->toArray(),
            );

            return JsonSuccessResponse::create([
                'households' => array_map(static function (Household $houseHold) {
                    return $houseHold->jsonSerialize();
                }, $orderedHouseholds),
                'invites' => array_map(static function (HouseholdInvite $invite) {
                    return $invite->jsonSerialize();
                }, $user->getInvites()),
                'user' => $user->jsonSerialize(),
                'checklistSubscriptions' => array_map(static function (Checklist $checklist) {
                    return $checklist->getUuid();
                }, $user->getChecklistSubscriptions()->toArray()),
                'settings' => $user->getUserSettings()->jsonSerialize(),
            ]);
        } catch (PersistenceException | \LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to load dashboard');
        }
    }
}

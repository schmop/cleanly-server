<?php

declare(strict_types=1);

namespace App\Controller;

use App\Analytics\ActivityType;
use App\Analytics\UsageTracker;
use App\Household\Entity\Household;
use App\Household\Entity\HouseholdInvite;
use App\Todo\Entity\Checklist;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/dashboard')]
class DashboardController extends UserAwareController
{
    public function __invoke(UsageTracker $tracker): Response
    {
        $user = $this->getUser();
        $tracker->track($user, ActivityType::AppOpen);

        return new JsonResponse([
            'households' => array_map(static function (Household $houseHold) {
                return $houseHold->jsonSerialize();
            }, $user->getHouseholds()),
            'invites' => array_map(static function (HouseholdInvite $invite) {
                return $invite->jsonSerialize();
            }, $user->getInvites()),
            'user' => $user->jsonSerialize(),
            'checklistSubscriptions' => array_map(static function (Checklist $checklist) {
                return $checklist->getUuid();
            }, $user->getChecklistSubscriptions()->toArray()),
            'settings' => $user->getUserSettings()->jsonSerialize(),
        ]);
    }
}

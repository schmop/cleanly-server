<?php

namespace App\Analytics;

use App\Analytics\Entity\UsageLog;
use App\User\Entity\User;
use App\Utils\Clock;
use App\Utils\UuidGenerator;

readonly class UsageTracker
{
    public function __construct(
        private UsageLogRepository $usageLogRepository,
        private Clock              $clock,
        private UuidGenerator      $uuidGenerator,
    ) {
    }

    public function track(User $user, ActivityType $activityType): void
    {
        $usageLog = new UsageLog(
            $this->uuidGenerator->v4(),
            $activityType,
            $user,
            $this->clock->now(),
        );
        $this->usageLogRepository->save($usageLog);
    }
}
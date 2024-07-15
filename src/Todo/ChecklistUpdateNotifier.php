<?php

namespace App\Todo;

use App\Push\Pusher;
use App\Todo\Entity\Checklist;
use App\User\Entity\User;
use App\Utils\Clock;

class ChecklistUpdateNotifier
{
    public function __construct(
        private readonly Pusher $pusher,
        private readonly Clock $clock,
    ) {
    }

    public function notify(User $updatingUser, Checklist $checklist): void
    {
        $lastUpdatedAt = $checklist->getLastUpdatedAt();
        $now = $this->clock->now();
        $checklist->setLastUpdatedAt($now);
        if ($lastUpdatedAt->add(new \DateInterval('PT30M')) < $now) {
            $this->pusher->publishChecklistUpdate($updatingUser, $checklist);
        }
    }
}
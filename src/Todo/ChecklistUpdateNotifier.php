<?php

namespace App\Todo;

use App\Push\Pusher;
use App\Todo\Entity\Checklist;
use App\User\Entity\User;
use App\Utils\Clock;

readonly class ChecklistUpdateNotifier
{
    public function __construct(
        private Pusher $pusher,
        private Clock  $clock,
        private ChecklistRepository $checklistRepository,
    ) {
    }

    public function notify(User $updatingUser, Checklist $checklist): void
    {
        $now = $this->clock->now();
        $lastUpdatedAt = $checklist->getLastUpdatedAt();
        $checklist->setLastUpdatedAt($now);
        $this->checklistRepository->save($checklist);
        if ($lastUpdatedAt->add(new \DateInterval('PT30M')) < $now) {
            $this->pusher->publishChecklistUpdate($updatingUser, $checklist);
        }
    }
}
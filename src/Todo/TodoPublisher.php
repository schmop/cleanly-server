<?php

namespace App\Todo;

use App\Hub\Publisher;
use App\Todo\Entity\Checklist;
use App\User\Entity\User;

readonly class TodoPublisher
{
    public function __construct(private Publisher $publisher)
    {
    }

    /**
     * @param TodoEvent[] $events
     */
    public function publish(User $updater, array $events, Checklist $checklist): void
    {
        $membersWithoutSender = array_values(
            array_udiff(
                $checklist->getHousehold()->getMembers()->toArray(),
                [$updater],
                static fn (User $a, User $b) => $a->getId() <=> $b->getId()
            )
        );

        $this->publisher->publish(
            $membersWithoutSender,
            'checklist',
            [
                'checklist_uuid' => $checklist->getUuid(),
                'events' => $events,
            ]
        );
    }
}

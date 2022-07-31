<?php

namespace App\Todo;
use App\Todo\Entity\Todo;
use App\Household\Entity\Household;
use Doctrine\ORM\EntityManagerInterface;
use App\Hub\Publisher;
use App\User\Entity\User;

class TodoPublisher
{
    public function __construct(private EntityManagerInterface $entityManager, private Publisher $publisher)
    {
    }

    /**
     * @var TodoEvent[] $events
     */
    public function publish(User $updater, array $events, Household $household): void
    {
        $membersWithoutSender = array_values(
            array_udiff(
                $household->getMembers()->toArray(),
                [$updater],
                fn(User $a, User $b) => $a->getId() - $b->getId()
            )
        );

        $this->publisher->publish(
            $membersWithoutSender,
            'checklist',
            [
                'household_id' => $household->getId(),
                'events' => $events,
            ]
        );
    }
}

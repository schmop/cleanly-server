<?php

namespace App\Todo;

use AlexCrawford\LexoRank\Rank;
use App\Household\Entity\Household;
use App\Todo\Entity\Checklist;
use App\Utils\Clock;
use App\Utils\UuidGenerator;

readonly class ChecklistFactory
{
    public function __construct(
        private UuidGenerator $uuidGenerator,
        private Clock         $clock,
    ) {
    }

    public function create(Household $home)
    {
        $sortrank = $home->getChecklists()->isEmpty()
            ? Rank::forEmptySequence()
            : Rank::after(
                Rank::fromString($home->getChecklists()->last()->getSortRank())
            )
        ;
        return new Checklist(
            $this->uuidGenerator->v4(),
            'New Checklist',
            $home,
            $this->clock->now(),
            $sortrank->get(),
        );
    }
}
<?php

namespace App\Todo;

use AlexCrawford\LexoRank\Rank;
use App\Todo\Entity\Checklist;

readonly class ChecklistSorter
{
    public function __construct(
        private ChecklistRepository $checklistRepository,
    ) {
    }

    public function moveAfter(Checklist $checklist, string|null $moveAfterUuid): void
    {
        $checklists = $checklist->getHousehold()->getChecklists();
        if ($checklists->isEmpty()) {
            return;
        }
        $moveAfter = $checklists->findFirst(fn(int $index, Checklist $list) => $list->getUuid() === $moveAfterUuid);
        $moveAfterIndex = $checklists->indexOf($moveAfter);
        if ($moveAfter === null || !is_int($moveAfterIndex)) {
            $this->moveAtStart($checklist);
            return;
        }
        $moveBefore = $checklists->get($moveAfterIndex + 1);
        if ($moveBefore === null) {
            $this->moveAtEnd($checklist);
            return;
        }
        $this->moveBetween($checklist, $moveAfter, $moveBefore);
    }

    private function moveBetween(Checklist $checklist, Checklist $moveAfter, Checklist $moveBefore): void
    {
        if ($moveAfter->getUuid() === $checklist->getUuid() || $moveBefore->getUuid() === $checklist->getUuid()) {
            // we are already at the desired position
            return;
        }
        $checklist->setSortRank(
            Rank::betweenRanks(
                Rank::fromString($moveAfter->getSortRank()),
                Rank::fromString($moveBefore->getSortRank())
            )->get()
        );
        $this->checklistRepository->save($checklist);
    }

    private function moveAtEnd(Checklist $checklist): void
    {
        $checklists = $checklist->getHousehold()->getChecklists();
        $lastChecklist = $checklists->last();
        if (!$lastChecklist || $lastChecklist->getUuid() === $checklist->getUuid()) {
            // we are already at the end
            return;
        }
        $checklist->setSortRank(
            Rank::after(
                Rank::fromString(
                    $lastChecklist->getSortRank()
                )
            )->get()
        );
        $this->checklistRepository->save($checklist);
    }

    private function moveAtStart(Checklist $checklist): void
    {
        $checklists = $checklist->getHousehold()->getChecklists();
        $firstChecklist = $checklists->first();
        if (!$firstChecklist || $firstChecklist->getUuid() === $checklist->getUuid()) {
            // we are already at the start
            return;
        }
        $checklist->setSortRank(
            Rank::before(
                Rank::fromString(
                    $firstChecklist->getSortRank()
                )
            )->get()
        );
        $this->checklistRepository->save($checklist);
    }
}
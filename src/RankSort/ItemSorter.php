<?php

namespace App\RankSort;

use AlexCrawford\LexoRank\Rank;

/**
 * @template T of RankSortableItem
 */
readonly class ItemSorter
{
    /**
     * @param RankSortableItemRepositoryInterface<T> $sortableRepository
     */
    public function __construct(
        private RankSortableItemRepositoryInterface $sortableRepository,
    ) {
    }

    /**
     * @param T $item
     */
    public function moveAfter(RankSortableItem $item, string|null $moveAfterUuid): void
    {
        if ($moveAfterUuid === null) {
            $this->moveAtEnd($item);
            return;
        }
        $moveAfterItem = $this->sortableRepository->findByUuid($moveAfterUuid);
        $moveBeforeItem = $this->sortableRepository->findAfter($item->getList(), $moveAfterUuid);
        if ($moveAfterItem === null || $moveBeforeItem === null) {
            $this->moveAtEnd($item);
            return;
        }
        $this->moveBetween($item, $moveAfterItem, $moveBeforeItem);
    }

    /**
     * @param T $item
     */
    public function moveBefore(RankSortableItem $item, string|null $moveBeforeUuid): void
    {
        if ($moveBeforeUuid === null) {
            $this->moveAtStart($item);
            return;
        }
        $moveBeforeItem = $this->sortableRepository->findByUuid($moveBeforeUuid);
        $moveAfterItem = $this->sortableRepository->findBefore($item->getList(), $moveBeforeUuid);
        if ($moveAfterItem === null || $moveBeforeItem === null) {
            $this->moveAtStart($item);
            return;
        }
        $this->moveBetween($item, $moveAfterItem, $moveBeforeItem);
    }

    /**
     * @param T $item
     * @param T $moveAfter
     * @param T $moveBefore
     */
    public function moveBetween(RankSortableItem $item, RankSortableItem $moveAfter, RankSortableItem $moveBefore): void
    {
        if ($moveAfter->getUuid() === $item->getUuid() || $moveBefore->getUuid() === $item->getUuid()) {
            // we are already at the desired position
            return;
        }
        $item->setSortRank(
            Rank::betweenRanks(
                Rank::fromString($moveAfter->getSortRank()),
                Rank::fromString($moveBefore->getSortRank())
            )->get()
        );
        $this->sortableRepository->save($item);
    }

    /**
     * @param T $item
     */
    public function moveAtEnd(RankSortableItem $item): void
    {
        $lastItem = $this->sortableRepository->findLast($item->getList());
        if (!$lastItem || $lastItem->getUuid() === $item->getUuid()) {
            // we are already at the end
            return;
        }
        $item->setSortRank(
            Rank::after(
                Rank::fromString(
                    $lastItem->getSortRank()
                )
            )->get()
        );
        $this->sortableRepository->save($item);
    }

    /**
     * @param T $item
     */
    public function moveAtStart(RankSortableItem $item): void
    {
        $firstItem = $this->sortableRepository->findFirst($item->getList());
        if (!$firstItem || $firstItem->getUuid() === $item->getUuid()) {
            // we are already at the start
            return;
        }
        $item->setSortRank(
            Rank::before(
                Rank::fromString(
                    $firstItem->getSortRank()
                )
            )->get()
        );
        $this->sortableRepository->save($item);
    }
}
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
     * Place $item after the item identified by $moveAfterUuid.
     * A null $moveAfterUuid means "move to the start" — there is no
     * item to land after. To move to the end, pass the current last item's uuid;
     * with no successor in the list moveBetween falls through to moveAtEnd.
     *
     * @param T $item
     */
    public function moveAfter(RankSortableItem $item, string|null $moveAfterUuid): void
    {
        if ($moveAfterUuid === null) {
            $this->moveAtStart($item);
            return;
        }
        $moveAfterItem = $this->sortableRepository->findByUuid($moveAfterUuid);
        if ($moveAfterItem === null) {
            $this->moveAtStart($item);
            return;
        }
        $moveBeforeItem = $this->sortableRepository->findAfter($item->getList(), $moveAfterUuid);
        if ($moveBeforeItem === null) {
            $this->moveAtEnd($item);
            return;
        }
        $this->moveBetween($item, $moveAfterItem, $moveBeforeItem);
    }

    /**
     * Place $item before the item identified by $moveBeforeUuid.
     * A null $moveBeforeUuid means "move to the end" — there is no successor to land before.
     * To move to the start, pass the current first item's uuid; with no predecessor in the
     * list moveBetween falls through to moveAtStart.
     *
     * @param T $item
     */
    public function moveBefore(RankSortableItem $item, string|null $moveBeforeUuid): void
    {
        if ($moveBeforeUuid === null) {
            $this->moveAtEnd($item);
            return;
        }
        $moveBeforeItem = $this->sortableRepository->findByUuid($moveBeforeUuid);
        if ($moveBeforeItem === null) {
            $this->moveAtEnd($item);
            return;
        }
        $moveAfterItem = $this->sortableRepository->findBefore($item->getList(), $moveBeforeUuid);
        if ($moveAfterItem === null) {
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
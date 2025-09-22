<?php

namespace App\RankSort;

/**
 * @template T of RankSortableItem
 */
interface RankSortableItemRepositoryInterface
{
    /**
     * @param RankSortableList<T> $list
     * @return T|null
     */
    public function findFirst(RankSortableList $list): RankSortableItem|null;

    /**
     * @param RankSortableList<T> $list
     * @return T|null
     */
    public function findLast(RankSortableList $list): RankSortableItem|null;

    /**
     * @return T|null
     */
    public function findByUuid(string $uuid): RankSortableItem|null;

    /**
     * @param T $item
     */
    public function save(RankSortableItem $item): void;

    /**
     * @param RankSortableList<T> $list
     * @return T|null
     */
    public function findAfter(RankSortableList $list, string $afterThisUuid): RankSortableItem|null;

    /**
     * @param RankSortableList<T> $list
     * @return T|null
     */
    public function findBefore(RankSortableList $list, string $beforeThisUuid): RankSortableItem|null;
}
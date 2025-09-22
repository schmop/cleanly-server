<?php

namespace App\RankSort;

interface RankSortableItem
{
    public function getUuid(): string;

    /**
     * @return non-empty-string
     */
    public function getSortRank(): string;

    /**
     * @return RankSortableList<static>
     */
    public function getList(): RankSortableList;

    public function setSortRank(string $sortRank): void;

}
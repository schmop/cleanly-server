<?php

namespace App\RankSort;

/**
 * @template T of RankSortableItem
 */
interface RankSortableList
{
    public function getUuid(): string;
}
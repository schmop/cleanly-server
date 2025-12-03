<?php

namespace App\Utils;

use SplPriorityQueue;

/**
 * @template TPriority The type of the priority values
 * @template TValue The type of the values stored in the priority queue
 */
class PriorityQueue
{
    /** @var SplPriorityQueue<TPriority, TValue> $queue */
    private SplPriorityQueue $queue;
    public function __construct()
    {
        $this->queue = new SplPriorityQueue();
        $this->queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);
    }

    /**
     * @param TValue $item
     * @param TPriority $priority
     * @return void
     */
    public function push(mixed $item, mixed $priority): void
    {
        $this->queue->insert($item, $priority);
    }

    /**
     * @return TValue
     */
    public function pop()
    {
        // The good 'ol trust-me-bro.
        // SPLs shit design with the extractionFlags breaks type-safety
        /** @var TValue $topOfStack */
        $topOfStack = $this->queue->extract();

        return $topOfStack;
    }

    public function isEmpty(): bool
    {
        return $this->queue->isEmpty();
    }
}
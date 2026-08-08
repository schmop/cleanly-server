<?php

declare(strict_types=1);

namespace App\Tests\Phunctional;

use App\Phunctional\Statistics;
use PHPUnit\Framework\TestCase;

class StatisticsTest extends TestCase
{
    public function testMin(): void
    {
        $this->assertSame(0, Statistics::min([9, 1, 9238, 0, 82]));
        $this->assertSame(-1000, Statistics::min([1000, 500, 0, -500, -1000]));
        $this->assertSame(7, Statistics::min([7]));
    }

    public function testMax(): void
    {
        $this->assertSame(9238, Statistics::max([9, 1, 9238, 0, 82]));
        $this->assertSame(1000, Statistics::max([1000, 500, 0, -500, -1000]));
        $this->assertSame(7, Statistics::max([7]));
    }

    public function testSum(): void
    {
        $this->assertSame(9330, Statistics::sum([9, 1, 9238, 0, 82]));
        $this->assertSame(0, Statistics::sum([1000, 500, 0, -500, -1000]));
    }

    public function testDelta(): void
    {
        $this->assertSame([1, 2, 2, 2, 75], Statistics::delta([0, 1, 3, 5, 7, 82]));
        $this->assertSame([-500, -500, -500, -500], Statistics::delta([1000, 500, 0, -500, -1000]));
    }

    public function testAverage(): void
    {
        $this->assertSame(2.0, Statistics::average([1, 2, 3]));
        $this->assertSame(2.5, Statistics::average([1, 2, 3, 4]));
        $this->assertSame(-1.0, Statistics::average([-3, 1]));
    }

    // --- Empty input: what a task with fewer than two completions produces ---

    public function testDeltaOfSingleValueIsEmpty(): void
    {
        // TaskLogRepository feeds delta() into min/max/average, so a task
        // completed exactly once lands on the empty-array path below.
        $this->assertSame([], Statistics::delta([42]));
        $this->assertSame([], Statistics::delta([]));
    }

    public function testMinOfEmptyArrayIsNull(): void
    {
        $this->assertNull(Statistics::min([]));
    }

    public function testMaxOfEmptyArrayIsNull(): void
    {
        $this->assertNull(Statistics::max([]));
    }

    public function testAverageOfEmptyArrayIsNull(): void
    {
        // Must be null rather than a division by zero.
        $this->assertNull(Statistics::average([]));
    }

    public function testSumOfEmptyArrayIsZero(): void
    {
        $this->assertSame(0, Statistics::sum([]));
    }
}

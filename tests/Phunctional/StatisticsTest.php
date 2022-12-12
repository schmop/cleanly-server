<?php

namespace App\Tests\Phunctional;

use App\Phunctional\Statistics;
use PHPUnit\Framework\TestCase;

class StatisticsTest extends TestCase
{
    public function testMin(): void
    {
        $this->assertSame(Statistics::min([9, 1, 9238, 0, 82]), 0);
        $this->assertSame(Statistics::min([1000, 500, 0, -500, -1000]), -1000);
    }

    public function testMax(): void
    {
        $this->assertSame(Statistics::max([9, 1, 9238, 0, 82]), 9238);
        $this->assertSame(Statistics::max([1000, 500, 0, -500, -1000]), 1000);
    }

    public function testSum(): void
    {
        $this->assertSame(Statistics::sum([9, 1, 9238, 0, 82]), 9330);
        $this->assertSame(Statistics::sum([1000, 500, 0, -500, -1000]), 0);
    }

    public function testDelta(): void
    {
        $this->assertSame(Statistics::delta([0, 1, 3, 5, 7, 82]), [1, 2, 2, 2, 75]);
        $this->assertSame(Statistics::delta([1000, 500, 0, -500, -1000]), [-500, -500, -500, -500]);
    }
}
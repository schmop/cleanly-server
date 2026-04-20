<?php

namespace App\Phunctional;

use Webmozart\Assert\Assert;
use function Lambdish\Phunctional\reduce;

class Statistics
{
    /**
     * @param array<int> $values
     * @return array<int>
     */
    static function delta(array $values): array
    {
        $deltas = [];
        for ($i = 1; $i < count($values); $i++) {
            $deltas[] = $values[$i] - $values[$i - 1];
        }

        return $deltas;
    }

    /**
     * @param array<int> $values
     * @throws \Webmozart\Assert\InvalidArgumentException
     */
    static function min(array $values): ?int
    {
        if (count($values) === 0) {
            return null;
        }

        $min = reduce(
            fn (int $acc, int $value) => ($value < $acc) ? $value : $acc, $values,
            PHP_INT_MAX
        );
        Assert::integer($min);

        return $min;
    }

    /**
     * @param array<int> $values
     * @throws \Webmozart\Assert\InvalidArgumentException
     */
    static function max(array $values): ?int
    {
        if (count($values) === 0) {
            return null;
        }

        $max = reduce(
            fn (int $acc, int $value) => ($value > $acc) ? $value : $acc,
            $values,
            PHP_INT_MIN
        );
        Assert::integer($max);

        return $max;
    }

    /**
     * @param array<int> $values
     * @throws \Webmozart\Assert\InvalidArgumentException
     */
    static function average(array $values): ?float
    {
        if (count($values) === 0) {
            return null;
        }

        return self::sum($values) / count($values);
    }

    /**
     * @param array<int> $values
     * @throws \Webmozart\Assert\InvalidArgumentException
     */
    static function sum(array $values): int
    {
        $sum = reduce(
            fn (int $acc, int $value) => $acc + $value,
            $values,
            0
        );
        Assert::integer($sum);

        return $sum;
    }
}

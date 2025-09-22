<?php

declare(strict_types=1);

namespace App\Json;

class JsonDecoder
{
    /**
     * @param int<1, max> $depth
     * @return array<array-key, mixed>
     * @throws \JsonException
     */
    public static function toArray(string $json, int $depth = 512): array
    {
        $array = json_decode($json, true, $depth, JSON_THROW_ON_ERROR);

        if (!is_array($array)) {
            throw new \JsonException('Returned value is not an array.');
        }

        return $array;
    }
}

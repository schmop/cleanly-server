<?php

namespace App\Statistics\Model;

use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;

readonly class StarStatisticsRequestContent
{
    private function __construct(
        public int $householdId,
        public string $begin,
        public string $end,
        public int $maxSampleCount,
    )
    {
    }

    /** @throws UnexpectedJsonException */
    public static function createFromJson(Json $json): self
    {
        return new self(
            $json->int('householdId'),
            $json->string('begin'),
            $json->string('end'),
            $json->int('maxSampleCount'),
        );
    }
}
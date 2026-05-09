<?php

declare(strict_types=1);

namespace App\Tests\Json;

use App\Json\JsonDecoder;
use PHPUnit\Framework\TestCase;

class JsonDecoderTest extends TestCase
{
    public function testDecodesObjectIntoArray(): void
    {
        $array = JsonDecoder::toArray('{"a":1,"b":"x"}');
        $this->assertSame(['a' => 1, 'b' => 'x'], $array);
    }

    public function testDecodesEmptyObject(): void
    {
        $this->assertSame([], JsonDecoder::toArray('{}'));
    }

    public function testThrowsOnMalformedJson(): void
    {
        $this->expectException(\JsonException::class);
        JsonDecoder::toArray('{not json');
    }

    public function testThrowsOnNonArrayRoot(): void
    {
        $this->expectException(\JsonException::class);
        // A bare string is valid JSON but not an array; toArray must reject it.
        JsonDecoder::toArray('"just a string"');
    }

    public function testThrowsOnNullRoot(): void
    {
        $this->expectException(\JsonException::class);
        JsonDecoder::toArray('null');
    }

    public function testRespectsDepthLimit(): void
    {
        // Depth 1 cannot contain a nested object.
        $this->expectException(\JsonException::class);
        JsonDecoder::toArray('{"a":{"b":1}}', 1);
    }
}

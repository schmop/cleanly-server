<?php

declare(strict_types=1);

namespace App\Tests\Json;

use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class JsonTest extends TestCase
{
    // -- fromString / fromRequest entry points --

    public function testFromStringParsesObject(): void
    {
        $json = Json::fromString('{"name":"Bob","age":30}');
        $this->assertSame('Bob', $json->string('name'));
        $this->assertSame(30, $json->int('age'));
    }

    public function testFromStringThrowsOnMalformedJson(): void
    {
        $this->expectException(UnexpectedJsonException::class);
        Json::fromString('{ broken');
    }

    public function testFromStringRejectsNonObjectRoot(): void
    {
        $this->expectException(UnexpectedJsonException::class);
        Json::fromString('[1,2,3]');
    }

    public function testFromRequestReadsBody(): void
    {
        $request = new Request(content: '{"k":"v"}');
        $json = Json::fromRequest($request);
        $this->assertSame('v', $json->string('k'));
    }

    // -- typed accessors --

    public function testStringAccessor(): void
    {
        $json = Json::fromString('{"k":"hello"}');
        $this->assertSame('hello', $json->string('k'));
    }

    public function testStringThrowsOnTypeMismatch(): void
    {
        $json = Json::fromString('{"k":42}');
        $this->expectException(UnexpectedJsonException::class);
        $json->string('k');
    }

    public function testStringThrowsOnMissing(): void
    {
        $json = Json::fromString('{}');
        $this->expectException(UnexpectedJsonException::class);
        $json->string('k');
    }

    public function testTryStringReturnsNullOnMissing(): void
    {
        $json = Json::fromString('{}');
        $this->assertNull($json->tryString('k'));
    }

    public function testTryStringThrowsOnTypeMismatch(): void
    {
        $json = Json::fromString('{"k":1}');
        $this->expectException(UnexpectedJsonException::class);
        $json->tryString('k');
    }

    public function testIntAccessor(): void
    {
        $json = Json::fromString('{"n":7}');
        $this->assertSame(7, $json->int('n'));
    }

    public function testIntRejectsNumericString(): void
    {
        $json = Json::fromString('{"n":"7"}');
        $this->expectException(UnexpectedJsonException::class);
        $json->int('n');
    }

    public function testTryIntReturnsNullOnMissing(): void
    {
        $json = Json::fromString('{}');
        $this->assertNull($json->tryInt('n'));
    }

    public function testBoolAccessor(): void
    {
        $json = Json::fromString('{"flag":true}');
        $this->assertTrue($json->bool('flag'));
    }

    public function testBoolRejectsTruthyInt(): void
    {
        $json = Json::fromString('{"flag":1}');
        $this->expectException(UnexpectedJsonException::class);
        $json->bool('flag');
    }

    public function testTryBoolReturnsNullOnMissing(): void
    {
        $json = Json::fromString('{}');
        $this->assertNull($json->tryBool('flag'));
    }

    // -- nested objects --

    public function testJsonAccessorReturnsChild(): void
    {
        $json = Json::fromString('{"inner":{"a":1}}');
        $inner = $json->json('inner');
        $this->assertSame(1, $inner->int('a'));
    }

    public function testJsonThrowsOnNonObject(): void
    {
        $json = Json::fromString('{"inner":"not-an-object"}');
        $this->expectException(UnexpectedJsonException::class);
        $json->json('inner');
    }

    public function testTryJsonReturnsNullOnMissing(): void
    {
        $json = Json::fromString('{}');
        $this->assertNull($json->tryJson('inner'));
    }

    public function testTryJsonReturnsNullOnEmptyArray(): void
    {
        // Empty array decodes to [] (sequential), which the helper treats as null.
        $json = Json::fromString('{"inner":[]}');
        $this->assertNull($json->tryJson('inner'));
    }

    // -- typed arrays --

    public function testJsonArray(): void
    {
        $json = Json::fromString('{"items":[{"id":1},{"id":2}]}');
        $items = $json->jsonArray('items');
        $this->assertCount(2, $items);
        $this->assertSame(1, $items[0]->int('id'));
        $this->assertSame(2, $items[1]->int('id'));
    }

    public function testJsonArrayRejectsAssociativeArray(): void
    {
        // jsonArray requires a list (sequential keys).
        $json = Json::fromString('{"items":{"a":{"id":1}}}');
        $this->expectException(UnexpectedJsonException::class);
        $json->jsonArray('items');
    }

    public function testStringArray(): void
    {
        $json = Json::fromString('{"tags":["a","b","c"]}');
        $this->assertSame(['a', 'b', 'c'], $json->stringArray('tags'));
    }

    public function testStringArrayRejectsMixedTypes(): void
    {
        $json = Json::fromString('{"tags":["a",1]}');
        $this->expectException(UnexpectedJsonException::class);
        $json->stringArray('tags');
    }

    public function testIntArray(): void
    {
        $json = Json::fromString('{"ids":[1,2,3]}');
        $this->assertSame([1, 2, 3], $json->intArray('ids'));
    }

    public function testIntArrayRejectsMixedTypes(): void
    {
        $json = Json::fromString('{"ids":[1,"2"]}');
        $this->expectException(UnexpectedJsonException::class);
        $json->intArray('ids');
    }

    // -- raw / serialize / keys --

    public function testKeys(): void
    {
        $json = Json::fromString('{"a":1,"b":2}');
        $this->assertSame(['a', 'b'], $json->keys());
    }

    public function testRawReturnsUnderlyingArray(): void
    {
        $json = Json::fromString('{"a":1,"b":[2,3]}');
        $this->assertSame(['a' => 1, 'b' => [2, 3]], $json->raw());
    }

    public function testSerializeRoundTrips(): void
    {
        $json = Json::fromString('{"a":1,"b":"x"}');
        $this->assertSame('{"a":1,"b":"x"}', $json->serialize());
    }
}

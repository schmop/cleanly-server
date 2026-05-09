<?php

declare(strict_types=1);

namespace App\Tests\HttpFoundation;

use App\HttpFoundation\JsonSuccessResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class JsonSuccessResponseTest extends TestCase
{
    public function testReturnsHttpOkWithEmptyBody(): void
    {
        $response = JsonSuccessResponse::create();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('[]', $response->getContent());
    }

    public function testEncodesData(): void
    {
        $response = JsonSuccessResponse::create(['ok' => true, 'count' => 3]);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('{"ok":true,"count":3}', $response->getContent());
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }

    public function testCustomHeadersArePassedThrough(): void
    {
        $response = JsonSuccessResponse::create([], ['X-Custom' => 'value']);
        $this->assertSame('value', $response->headers->get('X-Custom'));
    }
}

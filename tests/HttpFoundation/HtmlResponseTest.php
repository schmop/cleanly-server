<?php

declare(strict_types=1);

namespace App\Tests\HttpFoundation;

use App\HttpFoundation\HtmlResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class HtmlResponseTest extends TestCase
{
    public function testOk(): void
    {
        $response = HtmlResponse::ok('<h1>hi</h1>');
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('<h1>hi</h1>', $response->getContent());
    }

    public function testServerErrorDefaultBody(): void
    {
        $response = HtmlResponse::serverError();
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('Internal server error', $response->getContent());
    }

    public function testServerErrorCustomBody(): void
    {
        $response = HtmlResponse::serverError('boom');
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('boom', $response->getContent());
    }

    public function testWithStatusForwardsCode(): void
    {
        $response = HtmlResponse::withStatus('teapot', Response::HTTP_I_AM_A_TEAPOT);
        $this->assertSame(Response::HTTP_I_AM_A_TEAPOT, $response->getStatusCode());
        $this->assertSame('teapot', $response->getContent());
    }
}

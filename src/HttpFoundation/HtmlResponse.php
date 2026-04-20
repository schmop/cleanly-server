<?php

declare(strict_types=1);

namespace App\HttpFoundation;

use Symfony\Component\HttpFoundation\Response;

class HtmlResponse
{
    private function __construct()
    {
    }

    public static function ok(string $content): Response
    {
        // Status code is hardcoded valid; suppress Symfony's @throws InvalidArgumentException noise.
        // @phpstan-ignore missingType.checkedException
        return new Response($content, Response::HTTP_OK);
    }

    public static function serverError(string $content = 'Internal server error'): Response
    {
        // @phpstan-ignore missingType.checkedException
        return new Response($content, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function withStatus(string $content, int $statusCode): Response
    {
        // @phpstan-ignore missingType.checkedException
        return new Response($content, $statusCode);
    }
}

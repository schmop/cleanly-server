<?php

declare(strict_types=1);

namespace App\HttpFoundation;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class JsonSuccessResponse
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string> $headers
     */
    public static function create(array $data = [], array $headers = []): JsonResponse
    {
        return new JsonResponse($data, Response::HTTP_OK, $headers);
    }
}

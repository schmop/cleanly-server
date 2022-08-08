<?php

declare(strict_types=1);

namespace App\HttpFoundation;

use Symfony\Component\HttpFoundation\JsonResponse;

class JsonSuccessResponse
{
    private function __construct(){}

    public static function create(array $data = [], array $headers = []): JsonResponse
    {
        return new JsonResponse($data, JsonResponse::HTTP_OK, $headers);
    }
}
<?php

namespace App\HttpFoundation;

use Symfony\Component\HttpFoundation\JsonResponse;

class JsonErrorResponse
{
    private function __construct(){}

    public static function create(array $data = [], int $statusCode = JsonResponse::HTTP_BAD_REQUEST, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $statusCode, $headers);
    }
}
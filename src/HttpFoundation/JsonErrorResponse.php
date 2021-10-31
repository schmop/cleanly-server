<?php

namespace App\HttpFoundation;

use Symfony\Component\HttpFoundation\JsonResponse;

class JsonErrorResponse
{
    public static function create(array $data, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, JsonResponse::HTTP_BAD_REQUEST, $headers);
    }
}
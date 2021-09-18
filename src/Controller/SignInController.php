<?php

declare(strict_types=1);

namespace App\Controller;

use App\HttpFoundation\JsonSuccessResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/signin", "signin")
 */
class SignInController
{
    public function __invoke(): JsonResponse
    {
        return JsonSuccessResponse::create(["status" => "success"]);
    }
}
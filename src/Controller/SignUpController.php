<?php

declare(strict_types=1);

namespace App\Controller;

use App\HttpFoundation\JsonSuccessResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/signup", "signup")
 *
 * Validates a sign up request and creates a new user
 *
 * The request needs to contain the following to be valid:
 *  - a user name
 *  - an email-address
 *  - a password that has be deemed "secure"
 */
class SignUpController
{
    public function __invoke(): JsonResponse
    {
        return JsonSuccessResponse::create(["status" => "success"]);
    }
}
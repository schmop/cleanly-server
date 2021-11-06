<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/auth_check", "auth_check")
 */
class AuthCheckController extends AbstractController
{
    public function __invoke(): Response
    {
        return new Response();
    }
}
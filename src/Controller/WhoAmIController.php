<?php

declare(strict_types = 1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/whoami')]
class WhoAmIController extends UserAwareController
{
    public function __invoke(): Response
    {
        return new Response((string)$this->getUser()->getId());
    }
}
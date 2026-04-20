<?php

declare(strict_types = 1);

namespace App\Controller;

use App\HttpFoundation\HtmlResponse;
use App\HttpFoundation\JsonErrorResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/whoami')]
class WhoAmIController extends UserAwareController
{
    public function __invoke(LoggerInterface $logger): Response
    {
        try {
            return HtmlResponse::ok((string)$this->getUser()->getId());
        } catch (\LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to resolve current user');
        }
    }
}
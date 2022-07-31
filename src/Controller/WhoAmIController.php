<?php

declare(strict_types = 1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\User\Entity\User;

/**
 * @Route("/api/whoami", "whoami")
 */
class WhoAmIController extends AbstractController
{
    public function __invoke(): Response
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();
        return new Response((string)$user->getId());
    }
}
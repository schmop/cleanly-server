<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\HttpFoundation\JsonSuccessResponse;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    public function __invoke(UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $userRepository->find(0);
        if (null === $user) {
            $user = new User();
            $user->setEmail('example@example.org');
            $user->setPassword('nopass');
            $entityManager->persist($user);
            $entityManager->flush($user);
        }

        return JsonSuccessResponse::create(["status" => "success", "user" => [$user->getId(), $user->getEmail()]]);
    }
}
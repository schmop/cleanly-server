<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\SignUp\SignUpCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;


/**
 * @Route("/signup", "signup")
 *
 * Validates a sign up request and creates a new user
 *
 * The request needs to contain the following to be valid:
 *  - an email-address
 *  - a password that has be deemed "secure"
 */
class SignUpController
{
    public function __invoke(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse
    {
        $command = SignUpCommand::fromRequest($request);
        $errors = $validator->validate($command);
        if (count($errors) > 0) {
            return JsonErrorResponse::create(
                ['status' => 'error', 'errors' => (string)$errors],
                JsonResponse::HTTP_BAD_REQUEST,
                ['Access-Control-Allow-Origin' => '*']
            );
        }


        $user = new User($command->getMail(), $command->getName());
        $user->setPassword($passwordHasher->hashPassword($user, $command->getPassword()));

        $entityManager->persist($user);
        $entityManager->flush();

        return JsonSuccessResponse::create(
            ["status" => "success", "user" => [$user->getId(), $user->getMail()]],
            ['Access-Control-Allow-Origin' => '*']
        );
    }
}
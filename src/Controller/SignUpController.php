<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\SignUp\SignUpCommand;
use Doctrine\ORM\EntityManagerInterface;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;


/**
 * @Route("/signup", "signup", methods={"POST"})
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
        ValidatorInterface $validator,
    ): JsonResponse {
        $command = SignUpCommand::fromRequest($request);
        $emailValidator = new EmailValidator();
        $errors = $validator->validate($command);
        if (!$emailValidator->isValid($command->mail, new RFCValidation())) {
            $emailValidator->getError();
        }
        if (count($errors) > 0) {
            return JsonErrorResponse::create([
                'errors' => (string)$errors
            ]);
        }

        $user = new User($command->mail, $command->name);
        $user->setPassword($passwordHasher->hashPassword($user, $command->password));

        $entityManager->persist($user);
        $entityManager->flush();

        return JsonSuccessResponse::create(
            ["status" => "success", "user" => [$user->getId(), $user->getMail()]],
            ['Access-Control-Allow-Origin' => '*']
        );
    }
}
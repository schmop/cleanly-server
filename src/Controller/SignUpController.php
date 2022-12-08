<?php

declare(strict_types=1);

namespace App\Controller;

use App\User\Entity\User;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Registration\RegistrationFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Registration\RegistrationException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use App\Registration\RegistrationRepository;
use App\User\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class SignUpController
{
    #[Route("/signup", "signup", methods: ["POST"])]
    public function signup(
        Request $request,
        RegistrationFactory $registrationFactory,
        MailerInterface $mailer,
    ): JsonResponse {
        try {
            $registration = $registrationFactory->createRegistrationFromRequest($request);
        } catch (RegistrationException $e) {
            return JsonErrorResponse::create([
                'errors' => json_encode($e->errors),
            ]);
        }

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@schmoppo.de', 'Cleanly Bot'))
            ->to($registration->mail)
            ->subject('Your registration on cleanly')
            ->htmlTemplate('registration/email.html.twig')
            ->context([
                'registration' => $registration,
            ])
        ;

        $mailer->send($email);

        return JsonSuccessResponse::create(
            ["status" => "success"],
        );
    }

    #[Route("/verify/{uuid}/{token}", name: "signup_verify", methods: ["GET"])]
    public function verify(
        string $uuid,
        string $token,
        RegistrationRepository $registrationRepository,
        UserRepository $userRepository,
        Environment $twig,
    ): Response {
        $registration = $registrationRepository->findByUuid($uuid);
        if (null === $registration || $registration->token !== $token) {
            return JsonErrorResponse::create([
                'error' => 'Invalid registration token!'
            ], JsonResponse::HTTP_FORBIDDEN);
        }
        $user = new User($registration->mail, $registration->name);
        $user->setPassword($registration->password);
        $userRepository->save($user);
        $registrationRepository->remove($registration);

        return new Response($twig->render('registration/registration_success.html.twig'));
    }
}
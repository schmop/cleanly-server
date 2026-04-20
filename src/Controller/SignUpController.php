<?php

declare(strict_types=1);

namespace App\Controller;

use App\HttpFoundation\HtmlResponse;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Json\Exception\UnexpectedJsonException;
use App\Persistence\PersistenceException;
use App\Registration\RegistrationException;
use App\Registration\RegistrationFactory;
use App\Registration\RegistrationRepository;
use App\Template\TemplateRenderException;
use App\User\Entity\User;
use App\User\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class SignUpController
{
    #[Route("/signup", "signup", methods: ["POST"])]
    public function signup(
        Request             $request,
        RegistrationFactory $registrationFactory,
        MailerInterface     $mailer,
        LoggerInterface     $logger,
    ): JsonResponse {
        try {
            $registration = $registrationFactory->createRegistrationFromRequest($request);
        } catch (RegistrationException $e) {
            return JsonErrorResponse::create([
                'reason' => json_encode($e->errors),
                "status" => "failure",
            ]);
        } catch (UnexpectedJsonException $e) {
            return JsonErrorResponse::create([
                'reason' => 'Invalid JSON: ' . $e->getMessage(),
                "status" => "failure",
            ]);
        } catch (\RuntimeException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to create registration');
        }

        if (null !== $registration) {
            $email = (new TemplatedEmail())
                ->from(new Address('noreply@schmoppo.de', 'Cleanly Bot'))
                ->to($registration->mail)
                ->subject('Your registration on cleanly')
                ->htmlTemplate('registration/email.html.twig')
                ->context([
                    'registration' => $registration,
                ]);

            try {
                $mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                return JsonErrorResponse::fromException($logger, $e, 'Failed to send registration email');
            }
        }

        return JsonSuccessResponse::create(
            [
                "status" => "success",
                'verification_required' => null !== $registration,
            ],
        );
    }

    #[Route("/verify/{uuid}/{token}", name: "signup_verify", methods: ["GET"])]
    public function verify(
        string                 $uuid,
        string                 $token,
        RegistrationRepository $registrationRepository,
        UserRepository         $userRepository,
        Environment            $twig,
        LoggerInterface        $logger,
    ): Response {
        try {
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

            return HtmlResponse::ok(TemplateRenderException::render($twig, 'registration/registration_success.html.twig'));
        } catch (TemplateRenderException | PersistenceException $e) {
            $logger->error('Sign-up verification failed', ['exception' => $e]);
            return HtmlResponse::serverError();
        }
    }
}

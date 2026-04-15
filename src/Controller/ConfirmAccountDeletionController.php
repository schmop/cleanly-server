<?php

declare(strict_types=1);

namespace App\Controller;

use App\AccountDeletion\AccountDeletionRequestRepository;
use App\PasswordReset\ResetPasswordRequestRepository;
use App\Push\DeviceRepository;
use App\User\UserRepository;
use App\User\UserSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

#[Route("/account/delete/{uuid}/{token}", "confirm_account_deletion", methods: ["GET"])]
class ConfirmAccountDeletionController
{
    public function __invoke(
        string $uuid,
        string $token,
        AccountDeletionRequestRepository $repository,
        UserRepository $userRepository,
        UserSettingsRepository $userSettingsRepository,
        DeviceRepository $deviceRepository,
        ResetPasswordRequestRepository $resetPasswordRequestRepository,
        EntityManagerInterface $em,
        Environment $twig,
    ): Response {
        $deletionRequest = $repository->findByUuid($uuid);

        if (null === $deletionRequest || $deletionRequest->token !== $token) {
            return new Response(
                $twig->render('account_deletion/invalid.html.twig'),
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($deletionRequest->isExpired()) {
            $repository->remove($deletionRequest);

            return new Response(
                $twig->render('account_deletion/expired.html.twig'),
                Response::HTTP_GONE,
            );
        }

        $user = $deletionRequest->user;

        $userSettings = $userSettingsRepository->findOneBy(['user' => $user]);
        if (null !== $userSettings) {
            $em->remove($userSettings);
        }

        foreach ($deviceRepository->findByUser($user) as $device) {
            $em->remove($device);
        }

        foreach ($resetPasswordRequestRepository->findBy(['user' => $user]) as $resetRequest) {
            $em->remove($resetRequest);
        }

        $em->remove($deletionRequest);
        $em->remove($user);
        $em->flush();

        return new Response($twig->render('account_deletion/success.html.twig'));
    }
}

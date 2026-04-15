<?php

declare(strict_types=1);

namespace App\Controller;

use App\HttpFoundation\JsonSuccessResponse;
use App\PasswordReset\ResetPasswordRequestRepository;
use App\Push\DeviceRepository;
use App\User\UserFetcher;
use App\User\UserRepository;
use App\User\UserSettingsRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route("/api/user/account", "delete_account", methods: ["DELETE"])]
class DeleteAccountController
{
    public function __invoke(
        UserFetcher $userFetcher,
        UserRepository $userRepository,
        UserSettingsRepository $userSettingsRepository,
        DeviceRepository $deviceRepository,
        ResetPasswordRequestRepository $resetPasswordRequestRepository,
    ): Response {
        $user = $userFetcher->getUser();

        $userSettings = $user->getUserSettings();
        if ($userSettings->user === $user) {
            $userSettingsRepository->remove($userSettings);
        }

        foreach ($deviceRepository->findByUser($user) as $device) {
            $deviceRepository->getEntityManager()->remove($device);
        }
        $deviceRepository->getEntityManager()->flush();

        foreach ($resetPasswordRequestRepository->findBy(['user' => $user]) as $request) {
            $resetPasswordRequestRepository->getEntityManager()->remove($request);
        }
        $resetPasswordRequestRepository->getEntityManager()->flush();

        $userRepository->remove($user);

        return JsonSuccessResponse::create();
    }
}

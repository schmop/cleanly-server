<?php

declare(strict_types = 1);

namespace App\Controller;

use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Json\Json;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\User\UserFetcher;
use App\User\UserSettingsData;
use App\User\UserSettingsRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

#[Route("/api/user/settings", "user_settings")]
class SettingsController
{
    public function __invoke(
        Request $request,
        UserFetcher $userFetcher,
        UserSettingsRepository $userSettingsRepository,
        LoggerInterface $logger,
    ): Response {
        $user = $userFetcher->getUser();
        $settings = $user->getUserSettings();
        try {
            $settingsData = UserSettingsData::createFromJson(Json::fromRequest($request));
            $settingsData->applyTo($settings);
            $userSettingsRepository->save($settings);
        } catch (\Exception $e) {
            $logger->error('Could not save settings, reason: {reason}', [
                'reason' => $e->getMessage(),
                'exception' => $e
            ]);

            return JsonErrorResponse::create(['reason' => $e->getMessage()]);
        }

        return JsonSuccessResponse::create([]);
    }
}
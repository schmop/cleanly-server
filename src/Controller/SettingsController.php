<?php

declare(strict_types = 1);

namespace App\Controller;

use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use App\Persistence\PersistenceException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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
        try {
            $user = $userFetcher->getUser();
            $settings = $user->getUserSettings();
            try {
                $settingsData = UserSettingsData::createFromJson(Json::fromRequest($request));
                $settingsData->applyTo($settings);
                $userSettingsRepository->save($settings);
            } catch (UnexpectedJsonException | PersistenceException $e) {
                $logger->error('Could not save settings, reason: {reason}', [
                    'reason' => $e->getMessage(),
                    'exception' => $e
                ]);

                return JsonErrorResponse::create(['reason' => $e->getMessage()]);
            }

            return JsonSuccessResponse::create([]);
        } catch (\RuntimeException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to update user settings');
        }
    }
}
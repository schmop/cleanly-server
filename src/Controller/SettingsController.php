<?php

declare(strict_types = 1);

namespace App\Controller;

use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Json\Json;
use App\User\Entity\UserSettings;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\User\UserFetcher;
use App\User\UserSettingsData;
use App\User\UserSettingsRepository;
use Symfony\Component\HttpFoundation\Request;

#[Route("/api/user/settings", "user_settings")]
class SettingsController
{
    public function __invoke(Request $request, UserFetcher $userFetcher, UserSettingsRepository $userSettingsRepository): Response
    {
        $user = $userFetcher->getUser();
        $settings = $user->getUserSettings();
        try {
            $settingsData = UserSettingsData::createFromJson(Json::fromRequest($request)->json('settings'));
            $settingsData->applyTo($settings);
            $userSettingsRepository->save($settings);
        } catch (\Exception $e) {
            return JsonErrorResponse::create(['reason' => $e->getMessage()]);
        }

        return JsonSuccessResponse::create([]);
    }
}
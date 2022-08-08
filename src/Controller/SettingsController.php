<?php

declare(strict_types = 1);

namespace App\Controller;

use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
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
            $settingsData = UserSettingsData::createFromArray(json_decode($request->request->get('settings', '{}'), true, flags: JSON_THROW_ON_ERROR));
            $settingsData->applyTo($settings);
            $userSettingsRepository->save($settings);
        } catch (\Exception $e) {
            return JsonErrorResponse::create(['reason' => 'Invalid settings given!']);
        }

        return JsonSuccessResponse::create([]);
    }
}
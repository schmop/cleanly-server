<?php

declare(strict_types=1);

namespace App\Controller;

use App\Household\Entity\Household;
use App\Household\HouseholdRepository;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class StarController extends UserAwareController
{
    #[Route("/api/household/{id}/stars", "household_stars", methods: ["GET"])]
    public function fetchStarsInHousehold(
        Household           $household,
        HouseholdRepository $householdRepository,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$household->getMembers()->contains($user)) {
            return JsonErrorResponse::create(['reason' => 'You are not a member of this household!']);
        }

        return JsonSuccessResponse::create($householdRepository->retrieveStars($household));
    }
}

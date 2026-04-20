<?php

declare(strict_types=1);

namespace App\Controller;

use App\Household\Entity\Household;
use App\Household\HouseholdRepository;
use App\Household\HouseholdVoter;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class StarController extends UserAwareController
{
    #[Route("/api/household/{id}/stars", "household_stars", methods: ["GET"])]
    public function fetchStarsInHousehold(
        Household           $household,
        HouseholdRepository $householdRepository,
        LoggerInterface     $logger,
    ): JsonResponse {
        try {
            $this->denyAccessUnlessGranted(HouseholdVoter::READ_HOUSEHOLD_CONTENTS, $household);
            return JsonSuccessResponse::create($householdRepository->retrieveStars($household));
        } catch (\LogicException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to fetch stars');
        } catch (AccessDeniedException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Not enough privileges to fetch stars in this household');
        }
    }
}

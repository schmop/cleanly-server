<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Household;
use App\Entity\User;
use App\Repository\HouseholdRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/dashboard", "dashboard")
 */
class DashboardController extends AbstractController
{
    public function __invoke(HouseholdRepository $householdRepository): Response
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();

        return new JsonResponse(array_map(static function (Household $houseHold) {
            return $houseHold->jsonSerialize();
        }, $user->getHouseholds()));
    }
}
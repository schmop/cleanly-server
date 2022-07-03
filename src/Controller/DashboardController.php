<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Household;
use App\Entity\HouseholdInvite;
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
    public function __invoke(): Response
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();

        return new JsonResponse([
            'households' => array_map(static function (Household $houseHold) {
                return $houseHold->jsonSerialize();
            }, $user->getHouseholds()),
            'invites' => array_map(static function (HouseholdInvite $invite) {
                return $invite->jsonSerialize();
            }, $user->getInvites()),
            'user' => $user->jsonSerialize(),
        ]);
    }
}

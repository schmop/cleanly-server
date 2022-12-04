<?php

declare(strict_types=1);

namespace App\Controller;

use App\Household\Entity\Household;
use App\Household\Entity\HouseholdInvite;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\User\UserRepository;
use App\Utils\Base64UrlInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserController extends AbstractController
{
    #[Route(path: '/api/user/lookup', name: 'user_lookup', methods: ['POST'])]
    public function createHousehold(Request $request, UserRepository $userRepository): JsonResponse
    {
        $search = (string)$request->request->get('search');
        if (strlen($search) < 3) {
            return JsonSuccessResponse::create([]);
        }

        return JsonSuccessResponse::create($userRepository->search($search));
    }
}
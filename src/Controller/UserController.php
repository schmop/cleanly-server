<?php

declare(strict_types=1);

namespace App\Controller;

use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\User\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    #[Route(path: '/api/user/lookup', name: 'user_lookup', methods: ['POST'])]
    public function lookupUsers(Request $request, UserRepository $userRepository): JsonResponse
    {
        try {
            $search = (string)$request->request->get('search');
        } catch (BadRequestException $e) {
            return JsonErrorResponse::create(['reason' => $e->getMessage()]);
        }
        if (strlen($search) < 3) {
            return JsonSuccessResponse::create([]);
        }

        return JsonSuccessResponse::create($userRepository->search($search));
    }
}

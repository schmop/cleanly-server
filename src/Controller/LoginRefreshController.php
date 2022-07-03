<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\RefreshTokenRepository;
use App\HttpFoundation\JsonErrorResponse;
use App\Utils\Clock;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;


#[Route(path: "/api/login_refresh", name: "login_refresh", methods:["POST"])]
class LoginRefreshController extends AbstractController
{
    public function __invoke(
        Request $request,
        RefreshTokenRepository $refreshTokenRepository,
        Clock $clock,
        JWTTokenManagerInterface $jWTTokenManager,
    ): Response {
        $token = $request->request->get('refresh_token');
        $refreshToken = $refreshTokenRepository->findByToken($token);
        if (null === $refreshToken) {
            return JsonErrorResponse::create(
                ['error' => 'Invalid refresh token!'], 
                Response::HTTP_BAD_REQUEST
            );
        }
        if ($refreshToken->isOutdated($clock)) {
            return JsonErrorResponse::create(
                ['error' => 'You got logged out due to inactivity. Please login again!'], 
                Response::HTTP_FORBIDDEN
            );
        }
        $refreshToken->refresh($clock);
        $refreshTokenRepository->save($refreshToken);
        return new JsonResponse(['token' => $jWTTokenManager->create($refreshToken->getUser())]);
    }
}
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\RefreshTokenRepository;
use App\HttpFoundation\JsonErrorResponse;
use App\HttpFoundation\JsonSuccessResponse;
use App\Persistence\PersistenceException;
use App\Utils\Clock;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Auth\RefreshTokenRefresher;


#[Route(path: "/api/login_refresh", name: "login_refresh", methods:["POST"])]
class LoginRefreshController extends AbstractController
{
    public function __invoke(
        Request $request,
        RefreshTokenRepository $refreshTokenRepository,
        RefreshTokenRefresher $refreshTokenRefresher,
        Clock $clock,
        JWTTokenManagerInterface $jWTTokenManager,
        LoggerInterface $logger,
    ): Response {
        try {
            $token = $request->request->get('refresh_token');
            if (!is_string($token)) {
                return JsonErrorResponse::create(
                    ['error' => 'No refresh token given!'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
            $refreshToken = $refreshTokenRepository->findByToken($token);
            if (null === $refreshToken) {
                return JsonErrorResponse::create(
                    ['error' => 'Invalid refresh token!'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
            if ($refreshToken->isOutdated($clock)) {
                return JsonErrorResponse::create(
                    ['error' => 'You got logged out due to inactivity. Please login again!'],
                    Response::HTTP_FORBIDDEN,
                );
            }
            $refreshTokenRefresher->refresh($refreshToken);
            $refreshTokenRepository->save($refreshToken);
            return JsonSuccessResponse::create(['token' => $jWTTokenManager->create($refreshToken->getUser())]);
        } catch (PersistenceException | BadRequestException | \DateMalformedIntervalStringException $e) {
            return JsonErrorResponse::fromException($logger, $e, 'Failed to refresh login');
        }
    }
}
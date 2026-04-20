<?php

namespace App\Auth;

use App\Analytics\ActivityType;
use App\Analytics\UsageTracker;
use App\Persistence\PersistenceException;
use App\User\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

readonly class LoggingAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private AuthenticationSuccessHandler $lexikAuthenticationSuccessHandler,
        private UsageTracker                 $tracker,
    ) {
    }

    /**
     * @throws PersistenceException
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof User) {
            $this->tracker->track($user, ActivityType::Login);
        }

        return $this->lexikAuthenticationSuccessHandler->onAuthenticationSuccess($request, $token);
    }
}
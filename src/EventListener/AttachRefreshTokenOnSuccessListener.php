<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use App\Auth\RefreshTokenCreator;
use App\User\Entity\User;
use Psr\Log\LoggerInterface;

final class AttachRefreshTokenOnSuccessListener
{
    function __construct(
        private readonly RefreshTokenCreator $refreshTokenCreator,
        private readonly LoggerInterface $logger,
    ) {
	}

    public function attachRefreshToken(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!($user instanceof User)) {
            $this->logger->warning('Could not attach refresh token to requets!');

            return;
        }
        $data = $event->getData();
        $data['refresh_token'] = $this->refreshTokenCreator->create($user)->getToken();
        $event->setData($data);
    }
}

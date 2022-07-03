<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use App\Auth\RefreshTokenCreator;

final class AttachRefreshTokenOnSuccessListener
{
    function __construct(private RefreshTokenCreator $refreshTokenCreator) {
	}

    public function attachRefreshToken(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $data['refresh_token'] = $this->refreshTokenCreator->create($event->getUser())->getToken();
        $event->setData($data);
    }
}

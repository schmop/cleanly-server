<?php

namespace App\Controller;

use App\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

abstract class UserAwareController
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }
    public function getUser(): User
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!($user instanceof User)) {
            throw new \LogicException('Could not find logged in user!');
        }

        return $user;
    }

    /**
     * @throws AccessDeniedException
     */
    protected function denyAccessUnlessGranted(mixed $attribute, mixed $subject = null, string $message = 'Access Denied.'): void
    {
        if (!$this->authorizationChecker->isGranted($attribute, $subject)) {
            throw new AccessDeniedException($message);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\User;

use App\User\Entity\User;
use App\User\UserRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserFetcher {
    public function __construct(private TokenStorageInterface $tokenStorage, private UserRepository $userRepository)
    {
    }

    /**
     * @throws \RuntimeException
     */
    public function getUser(): User
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (null === $user) {
            throw new \RuntimeException('Could not load user');
        }
        $user = $this->userRepository->findOneBy(['mail' => $user->getUserIdentifier()]);

        if (null === $user) {
            throw new \RuntimeException('Could not load user');
        }

        return $user;
    }
}
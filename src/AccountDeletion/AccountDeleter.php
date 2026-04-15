<?php

declare(strict_types=1);

namespace App\AccountDeletion;

use App\PasswordReset\ResetPasswordRequestRepository;
use App\Push\DeviceRepository;
use App\User\Entity\User;
use App\User\UserSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

class AccountDeleter
{
    public function __construct(
        private readonly UserSettingsRepository $userSettingsRepository,
        private readonly DeviceRepository $deviceRepository,
        private readonly ResetPasswordRequestRepository $resetPasswordRequestRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function delete(User $user): void
    {
        $userSettings = $this->userSettingsRepository->findOneBy(['user' => $user]);
        if (null !== $userSettings) {
            $this->em->remove($userSettings);
        }

        foreach ($this->deviceRepository->findByUser($user) as $device) {
            $this->em->remove($device);
        }

        foreach ($this->resetPasswordRequestRepository->findBy(['user' => $user]) as $resetRequest) {
            $this->em->remove($resetRequest);
        }

        $this->em->remove($user);
        $this->em->flush();
    }
}

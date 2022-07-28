<?php

namespace App\Push;

use Kreait\Firebase\Contract\Messaging;
use App\Entity\Household;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Psr\Log\LoggerInterface;
use App\Entity\User;

class Pusher
{
    public function __construct(private Messaging $messaging, private DeviceRepository $deviceRepository, private LoggerInterface $logger)
    {
    }

    public function publishInHousehold(Household $household, string $title, string $content, ?string $imageUrl = null): void
    {
        $devices = $this->deviceRepository->findByHousehold($household);
        $this->publishToDevices($devices, $title, $content, $imageUrl);
    }

    /**
     * @param User[] $users
     */
    public function publishToUsers(array $users, string $title, string $content, ?string $imageUrl = null): void
    {
        $devices = $this->deviceRepository->findByUsers($users);
        $this->publishToDevices($devices, $title, $content, $imageUrl);
    }

    /**
     * @param string[] $devices
     */
    public function publishToDevices(array $devices, string $title, string $content, ?string $imageUrl = null): void
    {
        dump($devices);
        if (empty($devices)) {
            return;
        }
        try {
            $message = CloudMessage::new ()->withNotification(Notification::create($title, $content, $imageUrl));
            $this->messaging->sendMulticast($message, $devices);
        } catch (\Exception $e) {
            $this->logger->error('Could not send push notifications, reason: {message}!', [
                'message' => $e->getMessage(), 
                'exception' => $e
            ]);
        }
    }
}

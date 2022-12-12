<?php

namespace App\Push;

use Kreait\Firebase\Contract\Messaging;
use App\Household\Entity\Household;
use App\Push\Entity\Device;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Psr\Log\LoggerInterface;
use App\User\Entity\User;

use function Lambdish\Phunctional\filter;
use function Lambdish\Phunctional\map;

class Pusher
{
    public function __construct(private Messaging $messaging, private DeviceRepository $deviceRepository, private LoggerInterface $logger)
    {
    }

    public function publishTaskDone(Household $household, User $exclude, string $title, string $content): void
    {
        $devices = filter(
            fn(Device $device) =>
            $device->getUser() !== $exclude && $device->getUser()->getUserSettings()->notifyTaskDone === true,
            $this->deviceRepository->findByHousehold($household),
        );
        $this->publishToDevices($devices, $title, $content);
    }

    public function publishTaskDue(Household $household, string $title, string $content): void
    {
        $devices = filter(
            fn(Device $device) => $device->getUser()->getUserSettings()->notifyTaskDue === true,
            $this->deviceRepository->findByHousehold($household),
        );
        $this->publishToDevices($devices, $title, $content);
    }

    /**
     * @param User[] $users
     */
    public function publishInvites(array $users, string $title, string $content): void
    {
        $devices = filter(
            fn(Device $device) => $device->getUser()->getUserSettings()->notifyInvites === true,
            $this->deviceRepository->findByUsers($users),
        );
        $this->publishToDevices($devices, $title, $content);
    }

    /**
     * @param Device[] $devices
     */
    public function publishToDevices(array $devices, string $title, string $content, ?string $imageUrl = null): void
    {
        if (empty($devices)) {
            return;
        }
        $deviceIds = map(fn(Device $device) => $device->getPushId(), $devices);
        try {
            $message = CloudMessage::new()->withNotification(Notification::create($title, $content, $imageUrl));
            $this->messaging->sendMulticast($message, $deviceIds);
        } catch (\Exception $e) {
            $this->logger->error('Could not send push notifications, reason: {message}!', [
                'message' => $e->getMessage(),
                'exception' => $e
            ]);
        }
    }
}

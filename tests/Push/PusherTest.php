<?php

declare(strict_types=1);

namespace App\Tests\Push;

use App\Push\DeviceRepository;
use App\Push\Pusher;
use App\Utils\UuidGenerator;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MulticastSendReport;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Pure-unit coverage for `Pusher`. The full payload-shape assertions live
 * with the Android client; here we lock the invariants that have caused
 * silent regressions in the past:
 *
 * - Empty device sets must not hit the FCM transport at all.
 * - Transport errors must be caught and logged, never re-thrown.
 */
class PusherTest extends TestCase
{
    public function testPublishTestNotificationNoOpsWhenUserHasNoDevices(): void
    {
        $messaging = $this->createMock(Messaging::class);
        // The transport must never be called when there are no devices.
        $messaging->expects($this->never())->method('sendMulticast');

        $deviceRepository = $this->createMock(DeviceRepository::class);
        $deviceRepository->method('findBy')->willReturn([]);

        $logger = $this->createMock(LoggerInterface::class);
        $uuidGenerator = $this->createMock(UuidGenerator::class);
        $uuidGenerator->method('v4')->willReturn('test-uuid');

        $pusher = new Pusher($messaging, $deviceRepository, $logger, $uuidGenerator);
        $pusher->publishTestNotification(receiverId: 42);
    }

    public function testTransportFailureIsLoggedAndSwallowed(): void
    {
        $messaging = $this->createMock(Messaging::class);
        $messaging->method('sendMulticast')
            ->willThrowException(new NotFound('FCM said no'));

        $device = $this->createMock(\App\Push\Entity\Device::class);
        $device->method('getPushId')->willReturn('push-id-1');

        $deviceRepository = $this->createMock(DeviceRepository::class);
        $deviceRepository->method('findBy')->willReturn([$device]);

        $logger = $this->createMock(LoggerInterface::class);
        // The transport failure must surface as an `error` log, not bubble up.
        $logger->expects($this->atLeastOnce())
            ->method('error')
            ->with(
                $this->stringContains('Could not send push notifications'),
                $this->callback(fn(array $ctx): bool => isset($ctx['exception']) && $ctx['exception'] instanceof NotFound),
            );

        $uuidGenerator = $this->createMock(UuidGenerator::class);
        $uuidGenerator->method('v4')->willReturn('test-uuid');

        $pusher = new Pusher($messaging, $deviceRepository, $logger, $uuidGenerator);
        // Must not throw.
        $pusher->publishTestNotification(receiverId: 42);
    }

    public function testSuccessfulSendLogsCountsAndDoesNotError(): void
    {
        $device = $this->createMock(\App\Push\Entity\Device::class);
        $device->method('getPushId')->willReturn('push-id-1');

        $emptyReport = MulticastSendReport::withItems([]);

        $messaging = $this->createMock(Messaging::class);
        $messaging->expects($this->once())
            ->method('sendMulticast')
            ->willReturn($emptyReport);

        $deviceRepository = $this->createMock(DeviceRepository::class);
        $deviceRepository->method('findBy')->willReturn([$device]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $uuidGenerator = $this->createMock(UuidGenerator::class);
        $uuidGenerator->method('v4')->willReturn('test-uuid');

        $pusher = new Pusher($messaging, $deviceRepository, $logger, $uuidGenerator);
        $pusher->publishTestNotification(receiverId: 42);
    }
}

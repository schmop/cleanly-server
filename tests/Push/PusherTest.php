<?php

declare(strict_types=1);

namespace App\Tests\Push;

use App\Household\Entity\Household;
use App\Push\DeviceRepository;
use App\Push\Entity\Device;
use App\Push\Pusher;
use App\Task\Entity\Task;
use App\User\Entity\User;
use App\User\Entity\UserSettings;
use App\Utils\UuidGenerator;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
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
        $device = $this->createMock(Device::class);
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

    public function testTaskDoneNotificationCreditsTheCompleterNotTheClicker(): void
    {
        // Scenario: moderator Alice records a completion on behalf of Bob.
        // Charlie is a third member who should receive the push.
        $alice = $this->buildUser('Alice', notifyTaskDone: true);
        $bob = $this->buildUser('Bob', notifyTaskDone: true);
        $charlie = $this->buildUser('Charlie', notifyTaskDone: true);

        $household = $this->createMock(Household::class);
        $household->method('getName')->willReturn('Shared Flat');
        $household->method('getId')->willReturn(7);

        $task = $this->createMock(Task::class);
        $task->method('getName')->willReturn('Take out trash');
        $task->method('getId')->willReturn(13);
        $task->method('getHousehold')->willReturn($household);

        $deviceRepository = $this->createMock(DeviceRepository::class);
        $deviceRepository->method('findByHousehold')->willReturn([
            $this->buildDevice($alice, 'alice-push-id'),
            $this->buildDevice($bob, 'bob-push-id'),
            $this->buildDevice($charlie, 'charlie-push-id'),
        ]);

        $capturedMessages = [];
        $capturedRecipients = [];
        $messaging = $this->createMock(Messaging::class);
        $messaging->method('sendMulticast')
            ->willReturnCallback(function (CloudMessage $message, array $deviceIds) use (&$capturedMessages, &$capturedRecipients): MulticastSendReport {
                $capturedMessages[] = $message;
                $capturedRecipients[] = $deviceIds;
                return MulticastSendReport::withItems([]);
            });

        $pusher = new Pusher(
            $messaging,
            $deviceRepository,
            $this->createMock(LoggerInterface::class),
            $this->createMock(UuidGenerator::class),
        );

        $pusher->publishTaskDone($task, completer: $bob, clicker: $alice);

        // Only Charlie should be paged: Bob is credited (already attributed),
        // Alice just clicked the button (already knows).
        $this->assertNotEmpty($capturedRecipients);
        $this->assertSame(['charlie-push-id'], $capturedRecipients[0]);

        $payload = $capturedMessages[0]->jsonSerialize()['data'] ?? null;
        $this->assertIsArray($payload);
        $this->assertSame('task_done', $payload['type']);
        $this->assertStringContainsString('Bob', (string)$payload['body']);
        $this->assertStringNotContainsString('Alice', (string)$payload['body']);
    }

    public function testTaskDoneNotificationFallsBackToSelfWhenNoClicker(): void
    {
        // Self-completion: the credited user is the one who clicked, exclude only them.
        $alice = $this->buildUser('Alice', notifyTaskDone: true);
        $bob = $this->buildUser('Bob', notifyTaskDone: true);

        $household = $this->createMock(Household::class);
        $household->method('getName')->willReturn('Shared Flat');
        $household->method('getId')->willReturn(7);

        $task = $this->createMock(Task::class);
        $task->method('getName')->willReturn('Take out trash');
        $task->method('getId')->willReturn(13);
        $task->method('getHousehold')->willReturn($household);

        $deviceRepository = $this->createMock(DeviceRepository::class);
        $deviceRepository->method('findByHousehold')->willReturn([
            $this->buildDevice($alice, 'alice-push-id'),
            $this->buildDevice($bob, 'bob-push-id'),
        ]);

        $capturedRecipients = [];
        $messaging = $this->createMock(Messaging::class);
        $messaging->method('sendMulticast')
            ->willReturnCallback(function (CloudMessage $message, array $deviceIds) use (&$capturedRecipients): MulticastSendReport {
                $capturedRecipients[] = $deviceIds;
                return MulticastSendReport::withItems([]);
            });

        $pusher = new Pusher(
            $messaging,
            $deviceRepository,
            $this->createMock(LoggerInterface::class),
            $this->createMock(UuidGenerator::class),
        );

        $pusher->publishTaskDone($task, completer: $alice);

        $this->assertSame(['bob-push-id'], $capturedRecipients[0]);
    }

    private function buildUser(string $name, bool $notifyTaskDone): User
    {
        $user = $this->createMock(User::class);
        $user->method('getName')->willReturn($name);
        // notifyTaskDue: false so revokeTaskDue's filter empties out and
        // we only inspect the publishTaskDone send.
        $settings = new UserSettings($user, notifyTaskDone: $notifyTaskDone, notifyTaskDue: false);
        $user->method('getUserSettings')->willReturn($settings);
        return $user;
    }

    private function buildDevice(User $owner, string $pushId): Device
    {
        $device = $this->createMock(Device::class);
        $device->method('getUser')->willReturn($owner);
        $device->method('getPushId')->willReturn($pushId);
        return $device;
    }
}

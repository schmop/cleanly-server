<?php

namespace App\Push;

use App\Household\Entity\Household;
use App\Push\Entity\Device;
use App\Task\Entity\Task;
use App\Todo\Entity\Checklist;
use App\User\Entity\User;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\SendReport;
use Psr\Log\LoggerInterface;
use function Lambdish\Phunctional\filter;
use function Lambdish\Phunctional\map;

readonly class Pusher
{
    public function __construct(
        private Messaging        $messaging,
        private DeviceRepository $deviceRepository,
        private LoggerInterface  $logger,
    ) {
    }

    public function publishChecklistUpdate(User $exclude, Checklist $checklist): void
    {
        $household = $checklist->getHousehold();
        $devices = filter(
            fn(Device $device) => $device->getUser() !== $exclude && $checklist->getSubscribers()->contains($device->getUser()),
            $this->deviceRepository->findByHousehold($household),
        );
        $this->publishToDevices(
            $devices,
            'Neuigkeiten in Checkliste!',
            sprintf('Checkliste "%s" in "%s" wurde aktualisiert!', $checklist->getName(), $household->getName()),
        );
    }

    public function publishTaskDone(Task $task, User $exclude): void
    {
        $devices = filter(
            fn(Device $device) => $device->getUser() !== $exclude && $device->getUser()->getUserSettings()->notifyTaskDone === true,
            $this->deviceRepository->findByHousehold($task->getHousehold()),
        );
        $this->publishToDevices(
            $devices,
            sprintf('%s wurde erledigt!', $task->getName()),
            sprintf(
                '%s hat in %s gerade %s erledigt!',
                $exclude->getName(),
                $task->getHousehold()->getName(),
                $task->getName(),
            ),
        );
    }

    public function publishTaskDue(Task $task): void
    {
        $household = $task->getHousehold();
        $devices = filter(
            fn(Device $device) => $device->getUser()->getUserSettings()->notifyTaskDue === true,
            $this->deviceRepository->findByHousehold($household),
        );
        $this->publishToDevices(
            $devices,
            'Aufgabe wird dringend!',
            sprintf(
                '%s sollte in %s bald erledigt werden!',
                $task->getName(),
                $household->getName(),
            ),
        );
    }

    /**
     * @param User[] $invitees
     */
    public function publishInvites(User $inviter, array $invitees, Household $household): void
    {
        $devices = filter(
            fn(Device $device) => $device->getUser()->getUserSettings()->notifyInvites === true,
            $this->deviceRepository->findByUsers($invitees),
        );
        $this->publishToDevices(
            $devices,
            "Einladung in Haushalt",
            sprintf(
                "Einladung von %s in den Haushalt %s erhalten",
                $inviter->getName(),
                $household->getName(),
            ),
        );
    }

    public function publishTaskAssign(Task $task, User $assignee): void
    {
        $this->publishToDevices(
            $this->deviceRepository->findByUser($assignee),
            'Aufgabe zugewiesen',
            sprintf(
                'Dir wurde %s in %s zugewiesen!',
                $task->getName(),
                $task->getHousehold()->getName(),
            ),
        );
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
            $report = $this->messaging->sendMulticast($message, $deviceIds);
            $this->logger->info('Push notification sent to {count} devices, {success} successful, {failed} failed!', [
                'count' => count($deviceIds),
                'success' => $report->successes()->count(),
                'failed' => $report->failures()->count(),
                'failures' => map(
                    fn(SendReport $report) => [
                        'message' => $report->error(),
                        'exception' => $report->error(),
                    ],
                    $report->failures()->getItems(),
                ),
            ]);
        } catch (MessagingException|FirebaseException $e) {
            $this->logger->error('Could not send push notifications, reason: {message}!', [
                'message' => $e->getMessage(),
                'exception' => $e
            ]);
        }
    }
}

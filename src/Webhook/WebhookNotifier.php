<?php

declare(strict_types=1);

namespace App\Webhook;

use App\Task\Entity\Task;
use App\User\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookNotifier
{
    public const ACTION_TASK_DONE = 'task_done';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notify(Task $task, User $doneBy): void
    {
        $household = $task->getHousehold();
        $url = $household->getWebhookUrl();
        $secret = $household->getWebhookSecret();
        if (null === $url || null === $secret) {
            return;
        }
        try {
            $this->client->request('POST', "$url/cleanly/v1/webhook", [
                'json' => [
                    'action' => self::ACTION_TASK_DONE,
                    'household' => [
                        'id' => $household->getId(),
                        'name' => $household->getName(),
                    ],
                    'task' => [
                        'id' => $task->getId(),
                        'name' => $task->getName(),
                        'stars' => $task->getStars(),
                    ],
                    'done_by' => [
                        'id' => $doneBy->getId(),
                        'name' => $doneBy->getName(),
                    ],
                ],
                'headers' => [
                    'authorization' => "Bearer $secret",
                ],
            ]);
        } catch (ClientException $exception) {
            $this->logger->warning('Could not notify webhook.', [
                'exception' => $exception,
                'household' => $household->getId(),
                'webhookUrl' => $url,
            ]);
        }
    }
}

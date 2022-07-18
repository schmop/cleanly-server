<?php

namespace App\Hub;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\User;
use Psr\Log\LoggerInterface;

class Publisher
{
    private const HOST = 'http://localhost:3333';

    public function __construct(private HttpClientInterface $client, private LoggerInterface $logger)
    {
    }

    /**
     * @var User[] $targets
     */
    public function publish(array $targets, string $type, mixed $payload): void
    {
        $targetIds = array_map(function (User $target) { return $target->getId(); }, $targets);
        $response = $this->client->request('POST', sprintf("%s/publish", self::HOST), [
            'json' => [
                'targets' => $targetIds,
                'data' => [
                    'payload' => $payload,
                    'type' => $type,
                ],
            ]
        ]);
        if ($response->getStatusCode() !== 200) {
            $this->logger->error('Could not publish!');
        }
    }
}

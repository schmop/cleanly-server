<?php

namespace App\Hub;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\User\Entity\User;
use Psr\Log\LoggerInterface;

use function Lambdish\Phunctional\map;

class Publisher
{
    private const HOST = 'http://localhost:3334';

    public function __construct(private HttpClientInterface $client, private LoggerInterface $logger)
    {
    }

    /**
     * @param User[] $targets
     */
    public function publish(array $targets, string $type, mixed $payload): void
    {
        $targetIds = map(fn (User $target) => $target->getId(), $targets);
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

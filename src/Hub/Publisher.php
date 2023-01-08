<?php

namespace App\Hub;

use App\User\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
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
        $targetIds = map(fn(User $target) => $target->getId(), $targets);
        try {
            $response = $this->client->request('POST', sprintf("%s/publish", self::HOST), [
                'json' => [
                    'targets' => $targetIds,
                    'data' => [
                        'payload' => $payload,
                        'type' => $type,
                    ],
                ]
            ]);
            if ($response->getStatusCode() !== Response::HTTP_OK) {
                $this->logger->error('Could not publish!');
            }
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Could not publish!', ['exception' => $e]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Hub;

use App\Hub\Publisher;
use App\User\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class PublisherTest extends TestCase
{
    private const HUB_URL = 'http://hub.local:3334';
    private const SECRET = 's3cret';

    public function testPublishPostsTargetIdsAndPayloadShape(): void
    {
        $alice = $this->userWithId(1);
        $bob = $this->userWithId(2);

        $client = $this->createMock(HttpClientInterface::class);
        $response = $this->okResponse();

        $capturedOptions = [];
        $client->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                self::HUB_URL . '/publish',
                $this->callback(function (array $options) use (&$capturedOptions): bool {
                    $capturedOptions = $options;
                    return true;
                }),
            )
            ->willReturn($response);

        $publisher = new Publisher($client, $this->createMock(LoggerInterface::class), self::HUB_URL, self::SECRET);
        $publisher->publish([$alice, $bob], 'task_done', ['taskId' => 7]);

        $this->assertSame([1, 2], $capturedOptions['json']['targets']);
        $this->assertSame('task_done', $capturedOptions['json']['data']['type']);
        $this->assertSame(['taskId' => 7], $capturedOptions['json']['data']['payload']);
        $this->assertSame('Bearer ' . self::SECRET, $capturedOptions['headers']['Authorization']);
    }

    public function testNon200ResponseLogsError(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_INTERNAL_SERVER_ERROR);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Could not publish!');

        $publisher = new Publisher($client, $logger, self::HUB_URL, self::SECRET);
        $publisher->publish([$this->userWithId(1)], 'task_done', null);
    }

    public function testTransportExceptionIsCaughtAndLogged(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(new class extends \Exception implements TransportExceptionInterface {});

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Could not publish!',
                $this->callback(fn(array $ctx): bool => isset($ctx['exception'])),
            );

        $publisher = new Publisher($client, $logger, self::HUB_URL, self::SECRET);
        // Must not throw — SSE failures cannot break the write path.
        $publisher->publish([$this->userWithId(1)], 'task_done', null);
    }

    public function testEmptyTargetListStillPostsButWithEmptyIds(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $captured = [];
        $client->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                self::HUB_URL . '/publish',
                $this->callback(function (array $options) use (&$captured): bool {
                    $captured = $options;
                    return true;
                }),
            )
            ->willReturn($this->okResponse());

        $publisher = new Publisher($client, $this->createMock(LoggerInterface::class), self::HUB_URL, self::SECRET);
        $publisher->publish([], 'noop', null);

        $this->assertSame([], $captured['json']['targets']);
    }

    private function okResponse(): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_OK);
        return $response;
    }

    private function userWithId(int $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        return $user;
    }
}

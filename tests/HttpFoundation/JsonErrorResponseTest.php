<?php

declare(strict_types=1);

namespace App\Tests\HttpFoundation;

use App\Household\NotInHouseholdException;
use App\HttpFoundation\JsonErrorResponse;
use App\Json\Exception\UnexpectedJsonException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class JsonErrorResponseTest extends TestCase
{
    public function testCreateDefaultsToBadRequest(): void
    {
        $response = JsonErrorResponse::create(['reason' => 'oops']);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame('{"reason":"oops"}', $response->getContent());
    }

    public function testCreateRespectsCustomStatus(): void
    {
        $response = JsonErrorResponse::create([], Response::HTTP_CONFLICT);
        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testFromExceptionMapsUnexpectedJsonTo400(): void
    {
        $response = JsonErrorResponse::fromException(new NullLogger(), new UnexpectedJsonException('bad input'));
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame('{"reason":"bad input"}', $response->getContent());
    }

    public function testFromExceptionMapsTypeErrorTo400(): void
    {
        $response = JsonErrorResponse::fromException(new NullLogger(), new \TypeError('bad type'));
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testFromExceptionMapsValueErrorTo400(): void
    {
        $response = JsonErrorResponse::fromException(new NullLogger(), new \ValueError('bad value'));
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testFromExceptionMapsAccessDeniedTo403(): void
    {
        $response = JsonErrorResponse::fromException(new NullLogger(), new AccessDeniedException('denied'));
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testFromExceptionMapsNotInHouseholdTo403(): void
    {
        $response = JsonErrorResponse::fromException(new NullLogger(), new NotInHouseholdException('outsider'));
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testFromExceptionLogsAndReturns500ForUnknownException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('custom context', $this->callback(fn(array $ctx): bool => $ctx['exception'] instanceof \RuntimeException));

        $response = JsonErrorResponse::fromException($logger, new \RuntimeException('boom'), 'custom context');

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('{"reason":"Internal server error"}', $response->getContent());
    }
}

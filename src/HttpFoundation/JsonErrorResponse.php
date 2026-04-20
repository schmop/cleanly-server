<?php

namespace App\HttpFoundation;

use App\Household\NotInHouseholdException;
use App\Json\Exception\UnexpectedJsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class JsonErrorResponse
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function create(array $data = [], int $statusCode = Response::HTTP_BAD_REQUEST, array $headers = []): JsonResponse
    {
        // Symfony's Response constructor declares @throws InvalidArgumentException for invalid
        // status codes. All call sites pass valid codes; suppress the false-positive here so
        // callers don't need to catch a noise exception.
        // @phpstan-ignore missingType.checkedException
        return new JsonResponse($data, $statusCode, $headers);
    }

    /**
     * Translates an exception thrown by a controller into a JSON error response.
     * User-input errors are returned to the caller; access errors map to 403;
     * everything else is logged and returns a generic 500.
     */
    public static function fromException(LoggerInterface $logger, \Throwable $e, string $context = 'Request failed'): JsonResponse
    {
        if ($e instanceof UnexpectedJsonException || $e instanceof \TypeError || $e instanceof \ValueError) {
            return self::create(['reason' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
        if ($e instanceof AccessDeniedException || $e instanceof NotInHouseholdException) {
            return self::create(['reason' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }
        $logger->error($context, ['exception' => $e]);
        return self::create(['reason' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

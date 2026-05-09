<?php

declare(strict_types=1);

namespace App\Tests\Utils;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fluent helper that wraps `KernelBrowser::request` for JSON endpoints so
 * tests don't repeat the `CONTENT_TYPE` server var + `json_encode` boilerplate
 * on every call.
 */
final class JsonRequestBuilder
{
    /** @var array<string, mixed> */
    private array $body = [];
    /** @var array<string, string> */
    private array $headers = [];

    private function __construct(
        private readonly KernelBrowser $client,
        private readonly string $method,
        private readonly string $uri,
    ) {
    }

    public static function post(KernelBrowser $client, string $uri): self
    {
        return new self($client, 'POST', $uri);
    }

    public static function get(KernelBrowser $client, string $uri): self
    {
        return new self($client, 'GET', $uri);
    }

    public static function delete(KernelBrowser $client, string $uri): self
    {
        return new self($client, 'DELETE', $uri);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function withBody(array $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * @throws \JsonException
     */
    public function send(): Response
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        foreach ($this->headers as $name => $value) {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $server[$key] = $value;
        }

        $this->client->request(
            $this->method,
            $this->uri,
            server: $server,
            content: $this->body === [] ? null : json_encode($this->body, JSON_THROW_ON_ERROR),
        );

        return $this->client->getResponse();
    }
}

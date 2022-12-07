<?php

declare(strict_types=1);

namespace App\Json;

use Symfony\Component\HttpFoundation\Request;

class Json
{
    /** @var array<string, mixed> $data */
    private readonly array $data;

    /**
     * Validates that $data is actually array<string, mixed>
     *
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        array $data,
        private readonly ?string $previousPath = null,
    ) {
        foreach (array_keys($data) as $key) {
            if (!is_string($key)) {
                throw $this->buildException($this->buildPath($key), 'string');
            }
        }
        $this->data = $data;
    }

    /**
     * @throws \JsonException
     */
    public static function fromString(string $json): self
    {
        return new self(JsonDecoder::toArray($json));
    }

    public static function fromRequest(Request $request): self
    {
        return self::fromString($request->getContent());
    }

    public function string(string $key): string
    {
        if (!isset($this->data[$key]) || !is_string($this->data[$key])) {
            throw $this->buildException($key, 'string');
        }

        return $this->data[$key];
    }

    public function tryString(string $key): ?string
    {
        if (!isset($this->data[$key])) {
            return null;
        }
        if (!is_string($this->data[$key])) {
            throw $this->buildException($key, 'string');
        }

        return $this->data[$key];
    }

    public function int(string $key): int
    {
        if (!isset($this->data[$key]) || !is_int($this->data[$key])) {
            throw $this->buildException($key, 'int');
        }

        return $this->data[$key];
    }

    public function tryInt(string $key): ?int
    {
        if (!isset($this->data[$key])) {
            return null;
        }
        if (!is_int($this->data[$key])) {
            throw $this->buildException($key, 'int');
        }

        return $this->data[$key];
    }

    public function bool(string $key): bool
    {
        if (!isset($this->data[$key]) || !is_bool($this->data[$key])) {
            throw $this->buildException($key, 'bool');
        }

        return $this->data[$key];
    }

    public function tryBool(string $key): ?bool
    {
        if (!isset($this->data[$key])) {
            return null;
        }
        if (!is_bool($this->data[$key])) {
            throw $this->buildException($key, 'bool');
        }

        return $this->data[$key];
    }

    public function json(string $key): Json
    {
        if (!isset($this->data[$key]) || !is_array($this->data[$key])) {
            throw $this->buildException($key, 'json object');
        }

        return new self($this->data[$key]);
    }

    public function tryJson(string $key): ?Json
    {
        if (!isset($this->data[$key])) {
            return null;
        }
        $array = $this->data[$key];
        if (!is_array($array)) {
            throw $this->buildException($key, 'json object');
        }

        return $array ? new self($array) : null;
    }

    /**
     * @return Json[]
     */
    public function jsonArray(string $key): array
    {
        $array = $this->data[$key] ?? null;
        if (!is_array($array)) {
            throw $this->buildException($key, 'array');
        }
        $this->assertSequential($key);

        $childPath = $this->buildPath($key);
        $children = [];
        foreach ($array as $childKey => $child) {
            if (!is_array($child)) {
                throw $this->buildException("$key.$childKey", 'json object');
            }
            $children[] = new Json($child, "$childPath.$childKey");
        }

        return $children;
    }

    /**
     * @return string[]
     */
    public function stringArray(string $key): array
    {
        $array = $this->data[$key] ?? null;
        if (!is_array($array)) {
            throw $this->buildException($key, 'array');
        }
        $this->assertSequential($key);

        $children = [];
        foreach ($array as $childKey => $child) {
            if (!is_string($child)) {
                throw $this->buildException("$key.$childKey", 'string');
            }
            $children[] = $child;
        }

        return $children;
    }

    /**
     * @return int[]
     */
    public function intArray(string $key): array
    {
        $array = $this->data[$key] ?? null;
        if (!is_array($array)) {
            throw $this->buildException($key, 'array');
        }
        $this->assertSequential($key);

        $children = [];
        foreach ($array as $childKey => $child) {
            if (!is_int($child)) {
                throw $this->buildException("$key.$childKey", 'int');
            }
            $children[] = $child;
        }

        return $children;
    }

    /**
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->data;
    }

    public function serialize(): string
    {
        try {
            return json_encode($this->data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Invalid internal Json object.', previous: $e);
        }
    }

    private function assertSequential(string $key): void
    {
        $array = $this->data[$key];
        if (!is_array($array) || array_keys($array) !== range(0, count($array) - 1)) {
            throw $this->buildException($key, 'sequential array');
        }
    }

    private function buildPath(string|int $key): string
    {
        return $this->previousPath ? "$this->previousPath.$key" : (string) $key;
    }

    private function buildException(string|int $key, string $expectedType): \InvalidArgumentException
    {
        $path = $this->buildPath($key);

        if (!isset($this->data[$key])) {
            return new \InvalidArgumentException(
                sprintf('Missing value at %s. %s expected.', $path, $expectedType)
            );
        }

        $type = gettype($this->data[$key]);
        return new \InvalidArgumentException(
            sprintf('Invalid type at %s. %s expected, %s got.', $path, $expectedType, $type)
        );
    }
}

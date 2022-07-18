<?php

namespace App\Todo;

class TodoEvent
{
    public const TYPE_CREATE = 'create';
    public const TYPE_UPDATE = 'update';
    public const TYPE_SORT = 'sort';
    public const TYPE_DELETE = 'delete';
    private const ALL_TYPES = [
        self::TYPE_CREATE,
        self::TYPE_UPDATE,
        self::TYPE_SORT,
        self::TYPE_DELETE
    ];

    public function __construct(
        public string $type, 
        public string $uuid, 
        public null|string $data
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function createFromData(array $data): self
    {
        if (!in_array($data['type'], self::ALL_TYPES)) {
            throw new InconsistentChecklistEventException(
                sprintf('Checklist event has an unknown type "%s"!', $data['type']),
            );
        }
        if (!is_string($data['uuid'])) {
            throw new InconsistentChecklistEventException('Checklist event needs a uuid!');
        }
        if (in_array($data['type'], [self::TYPE_UPDATE]) && !is_string($data['data'])) {
            throw new InconsistentChecklistEventException('Checklist event is missing additional information!');
        }

        return new self($data['type'], $data['uuid'], $data['data'] ?? null);
    }
}

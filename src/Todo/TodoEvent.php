<?php

namespace App\Todo;

use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;

class TodoEvent
{
    public const TYPE_CREATE = 'create';
    public const TYPE_UPDATE = 'update';
    public const TYPE_SORT = 'sort';
    public const TYPE_CHECK = 'check';
    public const TYPE_DELETE = 'delete';
    private const ALL_TYPES = [
        self::TYPE_CREATE,
        self::TYPE_UPDATE,
        self::TYPE_SORT,
        self::TYPE_DELETE,
        self::TYPE_CHECK,
    ];

    public function __construct(
        public string      $type,
        public string      $uuid,
        public string      $checklistUuid,
        public null|string $data
    ) {
    }

    /**
     * @throws UnexpectedJsonException
     * @throws InconsistentChecklistEventException
     */
    public static function createFromJson(Json $json): self
    {
        if (!in_array($json->string('type'), self::ALL_TYPES)) {
            throw new InconsistentChecklistEventException(
                sprintf('Checklist event has an unknown type "%s"!', $json->string('type')),
            );
        }

        return new self(
            $json->string('type'),
            $json->string('uuid'),
            $json->string('checklistUuid'),
            $json->tryString('data'),
        );
    }
}

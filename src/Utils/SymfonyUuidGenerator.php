<?php

namespace App\Utils;

use Symfony\Component\Uid\Uuid;

final class SymfonyUuidGenerator implements UuidGenerator
{

    public function v4(): string
    {
        return (string) Uuid::v4();
    }
}

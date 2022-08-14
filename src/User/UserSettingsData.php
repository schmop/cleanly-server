<?php

namespace App\User;

use App\User\Entity\UserSettings;

class UserSettingsData
{
    public function __construct(
        public readonly bool $notifyTaskDone,
        public readonly bool $notifyTaskDue,
        public readonly bool $notifyInvites,
        public readonly string $language,
    ) {
    }

    public static function createFromArray(array $data): self
    {
        if (!isset($data['notifyTaskDone'], $data['notifyTaskDue'], $data['notifyInvites'])) {
            throw new \InvalidArgumentException('Data not complete to describe user settings!');
        }

        return new self($data['notifyTaskDone'], $data['notifyTaskDue'], $data['notifyInvites'], $data['language']);
    }

    public function applyTo(UserSettings $userSettings): void
    {
        $userSettings->notifyInvites = $this->notifyInvites;
        $userSettings->notifyTaskDone = $this->notifyTaskDone;
        $userSettings->notifyTaskDue = $this->notifyTaskDue;
        $userSettings->language = $this->language;
    }
}

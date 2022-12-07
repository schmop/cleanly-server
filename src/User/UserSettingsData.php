<?php

namespace App\User;

use App\Json\Json;
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

    public static function createFromJson(Json $json): self
    {
        return new self(
            $json->bool('notifyTaskDone'),
            $json->bool('notifyTaskDue'),
            $json->bool('notifyInvites'),
            $json->string('language'),
        );
    }

    public function applyTo(UserSettings $userSettings): void
    {
        $userSettings->notifyInvites = $this->notifyInvites;
        $userSettings->notifyTaskDone = $this->notifyTaskDone;
        $userSettings->notifyTaskDue = $this->notifyTaskDue;
        $userSettings->language = $this->language;
    }
}

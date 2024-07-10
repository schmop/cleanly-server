<?php

namespace App\User;

use App\Json\Exception\UnexpectedJsonException;
use App\Json\Json;
use App\User\Entity\UserSettings;

readonly class UserSettingsData
{
    public function __construct(
        public bool   $notifyTaskDone,
        public bool   $notifyTaskDue,
        public bool   $notifyInvites,
        public bool   $swipeToFinishTasks,
        public string $language,
    ) {
    }

    /**
     * @throws UnexpectedJsonException
     */
    public static function createFromJson(Json $json): self
    {
        return new self(
            $json->bool('notifyTaskDone'),
            $json->bool('notifyTaskDue'),
            $json->bool('notifyInvites'),
            $json->bool('swipeToFinishTasks'),
            $json->string('language'),
        );
    }

    public function applyTo(UserSettings $userSettings): void
    {
        $userSettings->notifyInvites = $this->notifyInvites;
        $userSettings->notifyTaskDone = $this->notifyTaskDone;
        $userSettings->notifyTaskDue = $this->notifyTaskDue;
        $userSettings->swipeToFinishTasks = $this->swipeToFinishTasks;
        $userSettings->language = $this->language;
    }
}

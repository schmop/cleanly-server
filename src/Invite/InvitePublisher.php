<?php

namespace App\Invite;

use App\Entity\HouseholdInvite;
use App\Hub\Publisher;

class InvitePublisher
{
    public function __construct(private Publisher $publisher)
    {
    }

    public function publish(HouseholdInvite $invite): void
    {
        if ($invite->getInvitee() === null) {
            return;
        }
        $this->publisher->publish(
            [$invite->getInvitee()],
            'invites',
            [
                'invite' => $invite->jsonSerialize(),
            ]
        );
    }
}

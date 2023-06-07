<?php

namespace App\Household;

enum ReassignmentStrategy: string
{
    case None = 'none';
    case Unassign = 'unassign';
    case Rotate = 'rotate';
}

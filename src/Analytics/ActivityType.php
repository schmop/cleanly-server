<?php

namespace App\Analytics;

enum ActivityType: string
{
    case Login = "login";
    case AppOpen = "app_open";
}
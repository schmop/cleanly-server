<?php

namespace App\Webhook;

class WebhookValidator
{
    public static function isWebhookUrlValid(string $webhookUrl): bool
    {
        return preg_match("/^https:\/\/(((?!\-))(xn\-\-)?[a-z0-9\-_]{0,61}[a-z0-9]{1,1}\.)*(xn\-\-)?([a-z0-9\-]{1,61}|[a-z0-9\-]{1,30})\.[a-z]{2,}$/", $webhookUrl) === 1;
    }
}

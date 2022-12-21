<?php

namespace App\Webhook;

use App\Utils\Base64UrlInterface;
use App\Utils\Random;

class WebhookSecretGenerator
{
    public function __construct(
        private readonly Random $random,
        private readonly Base64UrlInterface $base64,
    ) {
    }

    public function generate(): string
    {
        return $this->base64->encode($this->random->getRandomString(32));
    }
}

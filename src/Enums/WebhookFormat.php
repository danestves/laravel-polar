<?php

// Listed in tools/generate-data.php ROOTS for future regeneration.

declare(strict_types=1);

namespace Danestves\LaravelPolar\Enums;

enum WebhookFormat: string
{
    case Raw = 'raw';
    case Discord = 'discord';
    case Slack = 'slack';
}

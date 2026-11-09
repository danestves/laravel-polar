<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Danestves\LaravelPolar\Enums\WebhookFormat;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class WebhookEndpointUpdate extends PolarData
{
    /**
     * @param  array<int, \Danestves\LaravelPolar\Enums\WebhookEventType|string>|null  $events
     */
    public function __construct(
        public readonly ?string $url = null,
        public readonly ?string $name = null,
        public readonly ?WebhookFormat $format = null,
        public readonly ?array $events = null,
        public readonly ?bool $enabled = null,
    ) {}
}

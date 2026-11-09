<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Danestves\LaravelPolar\Enums\WebhookFormat;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class WebhookEndpointCreate extends PolarData
{
    /**
     * @param  array<int, \Danestves\LaravelPolar\Enums\WebhookEventType|string>  $events
     */
    public function __construct(
        public readonly string $url,
        public readonly WebhookFormat $format,
        public readonly array $events,
        public readonly ?string $name = null,
        public readonly ?string $organizationId = null,
    ) {}
}

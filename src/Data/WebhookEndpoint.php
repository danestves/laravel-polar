<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Carbon\CarbonImmutable;
use Danestves\LaravelPolar\Enums\WebhookFormat;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class WebhookEndpoint extends PolarData
{
    /**
     * @param  array<int, \Danestves\LaravelPolar\Enums\WebhookEventType|string>  $events
     */
    public function __construct(
        public readonly CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $modifiedAt,
        public readonly string $id,
        public readonly string $url,
        public readonly ?string $name,
        public readonly WebhookFormat $format,
        public readonly string $secret,
        public readonly string $organizationId,
        public readonly array $events,
        public readonly bool $enabled,
    ) {}
}

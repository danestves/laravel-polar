<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Carbon\CarbonImmutable;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class WebhookDelivery extends PolarData
{
    public function __construct(
        public readonly CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $modifiedAt,
        public readonly string $id,
        public readonly bool $succeeded,
        public readonly ?int $httpCode,
        public readonly ?string $response,
        public readonly WebhookEvent $webhookEvent,
    ) {}
}

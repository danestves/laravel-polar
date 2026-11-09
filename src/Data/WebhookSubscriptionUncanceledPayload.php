<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Carbon\CarbonImmutable;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Sent when a subscription is uncanceled.
 */
#[MapName(SnakeCaseMapper::class)]
class WebhookSubscriptionUncanceledPayload extends PolarData
{
    public function __construct(
        public readonly CarbonImmutable $timestamp,
        public readonly Subscription $data,
        public readonly string $type = 'subscription.uncanceled',
    ) {}
}

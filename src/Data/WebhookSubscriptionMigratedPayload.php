<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Carbon\CarbonImmutable;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Sent when Polar takes over billing of a subscription migrated from another provider.
 *
 * `provider` and `provider_subscription_id` live on the webhook envelope, not inside `data`.
 */
#[MapName(SnakeCaseMapper::class)]
class WebhookSubscriptionMigratedPayload extends PolarData
{
    public function __construct(
        public readonly CarbonImmutable $timestamp,
        public readonly Subscription $data,
        public readonly string $provider,
        public readonly string $providerSubscriptionId,
        public readonly string $type = 'subscription.migrated',
    ) {}
}

<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Carbon\CarbonImmutable;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Sent when an order is refunded.
 */
#[MapName(SnakeCaseMapper::class)]
class WebhookOrderRefundedPayload extends PolarData
{
    public function __construct(
        public readonly CarbonImmutable $timestamp,
        public readonly Order $data,
        public readonly string $type = 'order.refunded',
    ) {}
}

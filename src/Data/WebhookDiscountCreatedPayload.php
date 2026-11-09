<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Carbon\CarbonImmutable;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Sent when a discount is created.
 */
#[MapName(SnakeCaseMapper::class)]
class WebhookDiscountCreatedPayload extends PolarData
{
    public function __construct(
        public readonly CarbonImmutable $timestamp,
        public readonly Discount $data,
        public readonly string $type = 'discount.created',
    ) {}
}

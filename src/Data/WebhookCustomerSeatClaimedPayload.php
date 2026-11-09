<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Carbon\CarbonImmutable;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Sent when a customer claims a seat invitation.
 */
#[MapName(SnakeCaseMapper::class)]
class WebhookCustomerSeatClaimedPayload extends PolarData
{
    public function __construct(
        public readonly CarbonImmutable $timestamp,
        public readonly CustomerSeat $data,
        public readonly string $type = 'customer_seat.claimed',
    ) {}
}

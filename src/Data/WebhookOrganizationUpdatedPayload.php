<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Carbon\CarbonImmutable;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Sent when an organization is updated.
 */
#[MapName(SnakeCaseMapper::class)]
class WebhookOrganizationUpdatedPayload extends PolarData
{
    public function __construct(
        public readonly CarbonImmutable $timestamp,
        public readonly Organization $data,
        public readonly string $type = 'organization.updated',
    ) {}
}

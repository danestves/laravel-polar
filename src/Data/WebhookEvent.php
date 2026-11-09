<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Carbon\CarbonImmutable;
use Danestves\LaravelPolar\Enums\WebhookEventType;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class WebhookEvent extends PolarData
{
    public function __construct(
        public readonly CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $modifiedAt,
        public readonly string $id,
        public readonly ?int $lastHttpCode,
        public readonly ?bool $succeeded,
        public readonly bool $skipped,
        public readonly ?string $payload,
        public readonly WebhookEventType $type,
        public readonly bool $isArchived,
    ) {}
}

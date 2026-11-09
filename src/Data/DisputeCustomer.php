<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Carbon\CarbonImmutable;
use Danestves\LaravelPolar\Enums\CustomerType;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class DisputeCustomer extends PolarData
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<mixed>|null  $taxId
     */
    public function __construct(
        public readonly string $id,
        public readonly CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $modifiedAt,
        public readonly array $metadata,
        public readonly bool $emailVerified,
        public readonly CustomerType $type,
        public readonly ?string $name,
        public readonly ?string $billingName,
        public readonly ?Address $billingAddress,
        public readonly ?array $taxId,
        public readonly string $organizationId,
        public readonly ?CarbonImmutable $deletedAt,
        public readonly ?CarbonImmutable $firstUserEventAt,
        public readonly ?string $avatarUrl,
        public readonly ?string $externalId = null,
        public readonly ?string $email = null,
        public readonly ?string $locale = null,
        public readonly ?string $defaultPaymentMethodId = null,
    ) {}
}

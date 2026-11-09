<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Carbon\CarbonImmutable;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class CustomerWallet extends PolarData
{
    public function __construct(
        public readonly string $id,
        public readonly CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $modifiedAt,
        public readonly string $customerId,
        public readonly int $balance,
        public readonly string $currency,
    ) {}
}

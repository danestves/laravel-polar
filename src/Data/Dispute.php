<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Data;

use Carbon\CarbonImmutable;
use Danestves\LaravelPolar\Enums\DisputeStatus;
use Danestves\LaravelPolar\Support\PolarData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class Dispute extends PolarData
{
    public function __construct(
        public readonly CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $modifiedAt,
        public readonly string $id,
        public readonly DisputeStatus $status,
        public readonly bool $resolved,
        public readonly bool $closed,
        public readonly int $amount,
        public readonly int $taxAmount,
        public readonly string $currency,
        public readonly ?string $reason,
        public readonly ?CarbonImmutable $evidenceDueBy,
        public readonly bool $pastDue,
        public readonly string $orderId,
        public readonly string $paymentId,
        public readonly DisputeCustomer $customer,
        public readonly ?string $caseId,
    ) {}
}

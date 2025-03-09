<?php

namespace Danestves\LaravelPolar\Data\Products;

use Spatie\LaravelData\Attributes\MapName;

class LegacyRecurringProductPriceFreeData extends LegacyRecurringProductPriceData
{
    public function __construct(
        /**
         * Allowed value: `"free"`
         */
        #[MapName('amount_type')]
        public readonly string $amountType,
    ) {}
}

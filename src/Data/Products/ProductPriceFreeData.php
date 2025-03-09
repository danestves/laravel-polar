<?php

namespace Danestves\LaravelPolar\Data\Products;

use Spatie\LaravelData\Attributes\MapName;

class ProductPriceFreeData extends ProductPriceData
{
    public function __construct(
        /**
         * Allowed value: `"free"`
         */
        #[MapName('amount_type')]
        public readonly string $amountType,
    ) {}
}

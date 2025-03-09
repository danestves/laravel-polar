<?php

namespace Danestves\LaravelPolar\Data\Products;

use Spatie\LaravelData\Attributes\MapName;

class LegacyRecurringProductPriceCustomData extends LegacyRecurringProductPriceData
{
    public function __construct(
        /**
         * Allowed value: `"custom"`
         */
        #[MapName('amount_type')]
        public readonly string $amountType,
        /**
         * The currency.
         */
        #[MapName('price_currency')]
        public readonly string $priceCurrency,
        /**
         * The minimum amount the customer can pay.
         */
        #[MapName('minimum_amount')]
        public readonly ?int $minimumAmount,
        /**
         * The maximum amount the customer can pay.
         */
        #[MapName('maximum_amount')]
        public readonly ?int $maximumAmount,
        /**
         * The initial amount shown to the customer.
         */
        #[MapName('preset_amount')]
        public readonly ?int $presetAmount,
    ) {}
}

<?php

namespace Danestves\LaravelPolar\Data\Discounts;

use Spatie\LaravelData\Data;

class CheckoutDiscountFixedOnceForeverDurationData extends Data
{
    public function __construct(
        /**
         * Available options: `once`, `forever`, `repeating`
         */
        public readonly string $duration,
        /**
         * Available options: `fixed`, `percentage`
         */
        public readonly string $type,
        public readonly int $amount,
        public readonly string $currency,
        /**
         * The ID of the object.
         */
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $code,
    ) {}
}

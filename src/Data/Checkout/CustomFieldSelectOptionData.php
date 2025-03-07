<?php

namespace Danestves\LaravelPolar\Data\Checkout;

use Spatie\LaravelData\Data;

class CustomFieldSelectOptionData extends Data
{
    public function __construct(
        /**
         * Minimum length: `1`
         */
        public readonly string $value,
        /**
         * Minimum length: `1`
         */
        public readonly string $label,
    ) {}
}

<?php

namespace Danestves\LaravelPolar\Data\Discounts;

use Spatie\LaravelData\Attributes\MapName;

class CheckoutDiscountFixedRepeatDuration extends CheckoutDiscountFixedOnceForeverDurationData
{
    public function __construct(
        #[MapName('duration_in_months')]
        public readonly int $durationInMonths,
    ) {}
}

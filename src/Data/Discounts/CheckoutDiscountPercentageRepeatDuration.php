<?php

namespace Danestves\LaravelPolar\Data\Discounts;

use Spatie\LaravelData\Attributes\MapName;

class CheckoutDiscountPercentageRepeatDuration extends CheckoutDiscountPercentageOnceForeverDuration
{
    public function __construct(
        #[MapName('duration_in_months')]
        public readonly int $durationInMonths,
    ) {}
}

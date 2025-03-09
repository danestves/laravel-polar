<?php

namespace Danestves\LaravelPolar\Data\Discounts;

use Spatie\LaravelData\Attributes\MapName;

class CheckoutDiscountPercentageRepeatDurationData extends CheckoutDiscountPercentageOnceForeverDurationData
{
    public function __construct(
        #[MapName('duration_in_months')]
        public readonly int $durationInMonths,
    ) {}
}

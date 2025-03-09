<?php

namespace Danestves\LaravelPolar\Data\Subscriptions;

use Danestves\LaravelPolar\Enums\ProrationBehavior;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class SubscriptionUpdateProductData extends Data
{
    public function __construct(
        /**
         * Update subscription to another product.
         */
        #[MapName('product_id')]
        public readonly string $productId,
        /**
         * Determine how to handle the proration billing. If not provided, will use the default organization setting.
         *
         * Available options: `invoice`, `prorate`
         */
        #[MapName('proration_behavior')]
        public readonly ?ProrationBehavior $prorationBehavior,
    ) {}
}

<?php

namespace Danestves\LaravelPolar\Data\Subscriptions;

use Danestves\LaravelPolar\Enums\CustomerCancellationReason;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class SubscriptionCancelData extends Data
{
    public function __construct(
        /**
         * Cancel an active subscription once the current period ends.
         *
         * Or uncancel a subscription currently set to be revoked at period end.
         */
        #[MapName('cancel_at_period_end')]
        public readonly ?bool $cancelAtPeriodEnd,
        /**
         * Cancel and revoke an active subscription immediately.
         */
        public readonly ?bool $revoke,
        /**
         * Customer reason for cancellation.
         *
         * Helpful to monitor reasons behind churn for future improvements.
         *
         * Only set this in case your own service is requesting the reason from the
         * customer. Or you know based on direct conversations, i.e support, with
         * the customer.
         *
         * `too_expensive`: Too expensive for the customer.
         * `missing_features`: Customer is missing certain features.
         * `switched_service`: Customer switched to another service.
         * `unused`: Customer is not using it enough.
         * `customer_service`: Customer is not satisfied with the customer service.
         * `low_quality`: Customer is unhappy with the quality.
         * `too_complex`: Customer considers the service too complicated.
         * `other`: Other reason(s).
         *
         * Available options: `customer_service`, `low_quality`, `missing_features`, `switched_service`, `too_complex`, `too_expensive`, `unused`, `other`
         */
        #[MapName('customer_cancellation_reason')]
        public readonly ?CustomerCancellationReason $customerCancellationReason,
        /**
         * Customer feedback and why they decided to cancel.
         *
         * **IMPORTANT**:
         * Do not use this to store internal notes! It's intended to be input
         * from the customer and is therefore also available in their Polar
         * purchases library.
         *
         * Only set this in case your own service is requesting the reason from the
         * customer. Or you copy a message directly from a customer
         * conversation, i.e support.
         */
        #[MapName('customer_cancellation_comment')]
        public readonly ?string $customerCancellationComment,
    ) {}
}

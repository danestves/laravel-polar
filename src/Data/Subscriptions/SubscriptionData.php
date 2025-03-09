<?php

namespace Danestves\LaravelPolar\Data\Subscriptions;

use Danestves\LaravelPolar\Data\Customers\CustomerData;
use Danestves\LaravelPolar\Data\Discounts\CheckoutDiscountFixedOnceForeverDurationData;
use Danestves\LaravelPolar\Data\Discounts\CheckoutDiscountFixedRepeatDurationData;
use Danestves\LaravelPolar\Data\Discounts\CheckoutDiscountPercentageOnceForeverDurationData;
use Danestves\LaravelPolar\Data\Discounts\CheckoutDiscountPercentageRepeatDurationData;
use Danestves\LaravelPolar\Data\Products\LegacyRecurringProductPriceCustomData;
use Danestves\LaravelPolar\Data\Products\LegacyRecurringProductPriceFixedData;
use Danestves\LaravelPolar\Data\Products\LegacyRecurringProductPriceFreeData;
use Danestves\LaravelPolar\Data\Products\ProductData;
use Danestves\LaravelPolar\Data\Products\ProductPriceCustomData;
use Danestves\LaravelPolar\Data\Products\ProductPriceFixedData;
use Danestves\LaravelPolar\Data\Products\ProductPriceFreeData;
use Danestves\LaravelPolar\Data\Users\UserData;
use Danestves\LaravelPolar\Enums\CustomerCancellationReason;
use Danestves\LaravelPolar\Enums\RecurringInterval;
use Danestves\LaravelPolar\Enums\SubscriptionStatus;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class SubscriptionData extends Data
{
    public function __construct(
        /**
         * Creation timestamp of the object.
         */
        #[MapName('created_at')]
        public readonly string $createdAt,
        /**
         * Last modification timestamp of the object.
         */
        #[MapName('modified_at')]
        public readonly ?string $modifiedAt,
        /**
         * The ID of the subscription.
         */
        public readonly string $id,
        /**
         * The amount of the subscription.
         */
        public readonly ?int $amount,
        /**
         * The currency of the subscription.
         */
        public readonly ?string $currency,
        /**
         * The interval at which the subscription recurs.
         *
         * Available options: `month`, `year`
         */
        #[MapName('recurring_interval')]
        public readonly RecurringInterval $recurringInterval,
        /**
         * The status of the subscription.
         *
         * Available options: `incomplete`, `incomplete_expired`, `trialing`, `active`, `past_due`, `canceled`, `unpaid`
         */
        public readonly SubscriptionStatus $status,
        /**
         * The start timestamp of the current billing period.
         */
        #[MapName('current_period_start')]
        public readonly string $currentPeriodStart,
        /**
         * The end timestamp of the current billing period.
         */
        #[MapName('current_period_end')]
        public readonly ?string $currentPeriodEnd,
        /**
         * Whether the subscription will be canceled at the end of the current period.
         */
        #[MapName('cancel_at_period_end')]
        public readonly bool $cancelAtPeriodEnd,
        /**
         * The timestamp when the subscription was canceled. The subscription might still be active if `cancel_at_period_end` is `true`.
         */
        #[MapName('canceled_at')]
        public readonly ?string $canceledAt,
        /**
         * The timestamp when the subscription started.
         */
        #[MapName('started_at')]
        public readonly ?string $startedAt,
        /**
         * The timestamp when the subscription will end.
         */
        #[MapName('ends_at')]
        public readonly ?string $endsAt,
        /**
         * The timestamp when the subscription ended.
         */
        #[MapName('ended_at')]
        public readonly ?string $endedAt,
        /**
         * The ID of the subscribed customer.
         */
        #[MapName('customer_id')]
        public readonly string $customerId,
        /**
         * The ID of the subscribed product.
         */
        #[MapName('product_id')]
        public readonly string $productId,
        /**
         * The ID of the subscribed price.
         */
        #[MapName('price_id')]
        public readonly string $priceId,
        /**
         * The ID of the applied discount, if any.
         */
        #[MapName('discount_id')]
        public readonly ?string $discountId,
        #[MapName('checkout_id')]
        public readonly ?string $checkoutId,
        /**
         * Available options: `customer_service`, `low_quality`, `missing_features`, `switched_service`, `too_complex`, `too_expensive`, `unused`, `other`
         */
        #[MapName('customer_cancellation_reason')]
        public readonly ?CustomerCancellationReason $customerCancellationReason,
        #[MapName('customer_cancellation_comment')]
        public readonly ?string $customerCancellationComment,
        /** @var array<string, string|int|bool> */
        public readonly array $metadata,
        public readonly CustomerData $customer,
        /** @deprecated */
        public readonly string $userId,
        public readonly UserData $user,
        /**
         * A product.
         */
        public readonly ProductData $product,
        /**
         * A recurring price for a product, i.e. a subscription.
         *
         * @deprecated The recurring interval should be set on the product itself.
         */
        public readonly LegacyRecurringProductPriceFixedData|LegacyRecurringProductPriceCustomData|LegacyRecurringProductPriceFreeData|ProductPriceFixedData|ProductPriceCustomData|ProductPriceFreeData $price,
        public readonly CheckoutDiscountFixedOnceForeverDurationData|CheckoutDiscountFixedRepeatDurationData|CheckoutDiscountPercentageOnceForeverDurationData|CheckoutDiscountPercentageRepeatDurationData|null $discount,
        /** @var array<string, string|int|bool|\DateTime|null> */
        #[MapName('custom_field_data')]
        public readonly array $customFieldData,
    ) {}
}

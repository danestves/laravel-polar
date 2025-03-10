<?php

namespace Danestves\LaravelPolar\Data\Checkout;

use Danestves\LaravelPolar\Data\Customers\CustomerBillingAddressData;
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
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class CheckoutSessionData extends Data
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
         * The ID of the object.
         */
        public readonly string $id,
        /**
         * Payment processor used.
         *
         * Available options: `stripe`
         */
        #[MapName('payment_processor')]
        public readonly string $paymentProcessor,
        /**
         * Status of the checkout session.
         *
         * Available options: `open`, `expired`, `confirmed`, `succeeded`, `failed`
         */
        public readonly string $status,
        /**
         * Client secret used to update and complete the checkout session from the client.
         */
        #[MapName('client_secret')]
        public readonly string $clientSecret,
        /**
         * URL where the customer can access the checkout session.
         */
        public readonly string $url,
        /**
         * Expiration date and time of the checkout session.
         */
        #[MapName('expires_at')]
        public readonly string $expiresAt,
        /**
         * URL where the customer will be redirected after a successful payment.
         */
        #[MapName('success_url')]
        public readonly string $successUrl,
        /**
         * When checkout is embedded, represents the Origin of the page embedding the checkout.
         * Used as a security measure to send messages only to the embedding page.
         */
        #[MapName('embed_origin')]
        public readonly ?string $embedOrigin,
        /**
         * Amount to pay in cents. Only useful for custom prices,
         * it'll be ignored for fixed and free prices.
         *
         * Required range: `50 <= x <= 99999999`
         */
        public readonly ?int $amount,
        /**
         * Computed tax amount to pay in cents.
         */
        #[MapName('tax_amount')]
        public readonly ?int $taxAmount,
        /**
         * Currency code of the checkout session.
         */
        public readonly ?string $currency,
        /**
         * Subtotal amount in cents, including discounts and before tax.
         */
        #[MapName('subtotal_amount')]
        public readonly ?int $subtotalAmount,
        /**
         * Total amount to pay in cents, including discounts and after tax.
         */
        #[MapName('total_amount')]
        public readonly ?int $totalAmount,
        /**
         * ID of the product to checkout.
         */
        #[MapName('product_id')]
        public readonly ?string $productId,
        /**
         * ID of the product price to checkout.
         */
        #[MapName('product_price_id')]
        public readonly ?string $productPriceId,
        /**
         * ID of the discount applied to the checkout.
         */
        #[MapName('discount_id')]
        public readonly ?string $discountId,
        /**
         * Whether to allow the customer to apply discount codes.
         * If you apply a discount through discount_id, it'll still be applied,
         * but the customer won't be able to change it.
         */
        #[MapName('allow_discount_codes')]
        public readonly bool $allowDiscountCodes,
        /**
         * Whether the discount is applicable to the checkout.
         * Typically, free and custom prices are not discountable.
         */
        #[MapName('is_discount_applicable')]
        public readonly bool $isDiscountApplicable,
        /**
         * Whether the product price is free, regardless of discounts.
         */
        #[MapName('is_free_product_price')]
        public readonly ?bool $isFreeProductPrice,
        /**
         * Whether the checkout requires payment, e.g. in case of free products or discounts that cover the total amount.
         */
        #[MapName('is_payment_required')]
        public readonly bool $isPaymentRequired,
        /**
         * Whether the checkout requires setting up a payment method, regardless of the amount, e.g. subscriptions that have first free cycles.
         */
        #[MapName('is_payment_setup_required')]
        public readonly bool $isPaymentSetupRequired,
        /**
         * Whether the checkout requires a payment form, whether because of a payment or payment method setup.
         */
        #[MapName('is_payment_form_required')]
        public readonly bool $isPaymentFormRequired,
        /**
         * ID of an existing customer in the organization.
         */
        #[MapName('customer_id')]
        public readonly ?string $customerId,
        /**
         * ID of the customer in your system.
         */
        #[MapName('customer_external_id')]
        public readonly ?string $customerExternalId,
        /**
         * Name of the customer.
         */
        #[MapName('customer_name')]
        public readonly ?string $customerName,
        /**
         * Email of the customer.
         */
        #[MapName('customer_email')]
        public readonly ?string $customerEmail,
        /**
         * IP address of the customer.
         */
        #[MapName('customer_ip_address')]
        public readonly ?string $customerIpAddress,
        /**
         * Billing address of the customer.
         */
        #[MapName('customer_billing_address')]
        public readonly ?CustomerBillingAddressData $customerBillingAddress,
        /**
         * Tax ID of the customer.
         */
        #[MapName('customer_tax_id')]
        public readonly ?string $customerTaxId,
        /** @var array<string, string> */
        #[MapName('payment_processor_metadata')]
        public readonly array $paymentProcessorMetadata,
        /**
         * Key-value object allowing you to store additional information.
         *
         * The key must be a string with a maximum length of **40 characters**. The value must be either:
         *
         * - A string with a maximum length of **500 characters**
         * - An integer
         * - A boolean
         *
         * You can store up to **50 key-value pairs**.
         *
         * @var array<string, string|int>|null
         */
        public readonly ?array $metadata,
        /**
         * List of products available to select.
         *
         * Product data for a checkout session.
         *
         * @var array<ProductData>
         */
        public readonly array $products,
        /**
         * Product selected to checkout.
         */
        public readonly ProductData $product,
        /**
         * Price of the selected product.
         */
        #[MapName('product_price')]
        public readonly LegacyRecurringProductPriceFixedData|LegacyRecurringProductPriceCustomData|LegacyRecurringProductPriceFreeData|ProductPriceFixedData|ProductPriceCustomData|ProductPriceFreeData|null $productPrice,
        /**
         * Schema for a percentage discount that is applied once or forever.
         */
        public readonly CheckoutDiscountFixedOnceForeverDurationData|CheckoutDiscountFixedRepeatDurationData|CheckoutDiscountPercentageOnceForeverDurationData|CheckoutDiscountPercentageRepeatDurationData|null $discount,
        #[MapName('subscription_id')]
        public readonly ?string $subscriptionId,
        /**
         * Schema of a custom field attached to a resource.
         *
         * @var array<AttachedCustomFieldData>
         */
        #[MapName('attached_custom_fields')]
        public readonly array $attachedCustomFields,
        /** @var array<string, string|int|bool> */
        #[MapName('customer_metadata')]
        public readonly array $customerMetadata,
        /** @var array<string, string|int|bool|\DateTime|null> */
        #[MapName('custom_field_data')]
        public readonly ?array $customFieldData,
    ) {
        //
    }
}

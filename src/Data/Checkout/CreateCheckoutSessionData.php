<?php

namespace Danestves\LaravelPolar\Data\Checkout;

use Danestves\LaravelPolar\Data\Customers\CustomerBillingAddressData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use DateTime;

class CreateCheckoutSessionData extends Data
{
    public function __construct(
        /**
         * List of product IDs available to select at that checkout.
         * The first one will be selected by default.
         *
         * @var array<string>
         */
        public readonly array $products,
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
         * Key-value object storing custom field values.
         *
         * @var array<string, string|int|bool|DateTime|null>|null
         */
        #[MapName('custom_field_data')]
        public readonly ?array $customFieldData,
        /**
         * ID of the discount to apply to the checkout.
         */
        public readonly ?string $discountId,
        /**
         * Whether to allow the customer to apply discount codes.
         * If you apply a discount through discount_id, it'll still be applied,
         * but the customer won't be able to change it.
         */
        #[MapName('allow_discount_codes')]
        public readonly ?bool $allowDiscountCodes,
        /**
         * Amount to pay in cents. Only useful for custom prices,
         * it'll be ignored for fixed and free prices.
         *
         * Required range: `50 <= x <= 99999999`
         */
        public readonly ?int $amount,
        /**
         * ID of an existing customer in the organization.
         * The customer data will be pre-filled in the checkout form.
         * The resulting order will be linked to this customer.
         */
        #[MapName('customer_id')]
        public readonly ?string $customerId,
        /**
         * ID of the customer in your system.
         * If a matching customer exists on Polar, the resulting order will be linked to this customer.
         * Otherwise, a new customer will be created with this external ID set.
         */
        #[MapName('customer_external_id')]
        public readonly ?string $customerExternalId,
        /**
         * Name of the customer to be pre-filled in the checkout form.
         */
        #[MapName('customer_name')]
        public readonly ?string $customerName,
        /**
         * Email of the customer to be pre-filled in the checkout form.
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
         * @var array<string, string|int|bool|DateTime|null>|null
         */
        #[MapName('customer_metadata')]
        public readonly ?array $customerMetadata,
        /**
         * ID of a subscription to upgrade. It must be on a free pricing.
         * If checkout is successful, metadata set on this checkout will be copied to the subscription,
         * and existing keys will be overwritten.
         */
        #[MapName('subscription_id')]
        public readonly ?string $subscriptionId,
        /**
         * URL where the customer will be redirected after a successful payment.
         * You can add the `checkout_id={CHECKOUT_ID}` query parameter to retrieve the checkout session id.
         *
         * Required string length: `1 - 2083`
         */
        #[MapName('success_url')]
        public readonly ?string $successUrl,
        /**
         * If you plan to embed the checkout session, set this to the Origin of the embedding page.
         * It'll allow the Polar iframe to communicate with the parent page.
         */
        #[MapName('embed_origin')]
        public readonly ?string $embedOrigin,
    ) {
        //
    }
}

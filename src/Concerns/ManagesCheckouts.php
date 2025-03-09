<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\Checkout;
use Danestves\LaravelPolar\Data\Customers\CustomerBillingAddressData;

trait ManagesCheckouts // @phpstan-ignore-line trait.unused - ManagesCheckouts is used in Billable trait
{
    /**
     * Create a new checkout instance to sell a product.
     *
     * @param  array<string>  $products
     * @param  array<string, string|int>|null  $options
     * @param  array<string, string|int|bool>|null  $customerMetadata
     * @param  array<string, string|int|bool>|null  $metadata
     */
    public function checkout(array $products, ?array $options = [], ?array $customerMetadata = [], ?array $metadata = []): Checkout
    {
        /** @var string|int $key */
        $key = $this->getKey();

        // We'll need a way to identify the user in any webhook we're catching so before
        // we make an API request we'll attach the authentication identifier to this
        // checkout so we can match it back to a user when handling Polar webhooks.
        $customerMetadata = array_merge($customerMetadata, [
            'billable_id' => (string) $key,
            'billable_type' => $this->getMorphClass(),
        ]);

        /** @var CustomerBillingAddressData|null */
        $billingAddress = null;
        if (isset($options['country'])) {
            $billingAddress = new CustomerBillingAddressData(
                country: (string) $options['country'],
                line1: isset($options['line1']) ? (string) $options['line1'] : null,
                line2: isset($options['line2']) ? (string) $options['line2'] : null,
                postalCode: isset($options['zip']) ? (string) $options['zip'] : null,
                city: isset($options['city']) ? (string) $options['city'] : null,
                state: isset($options['state']) ? (string) $options['state'] : null,
            );
        }

        $checkout = Checkout::make($products)
            ->withCustomerName((string) ($options['customer_name'] ?? $this->polarName() ?? ''))
            ->withCustomerEmail((string) ($options['customer_email'] ?? $this->polarEmail() ?? ''))
            ->withCustomerBillingAddress($billingAddress)
            ->withCustomerMetadata($customerMetadata)
            ->withMetadata($metadata);

        if (isset($options['tax_id'])) {
            $checkout->withCustomerTaxId((string) $options['tax_id']);
        }

        if (isset($options['discount_id'])) {
            $checkout->withDiscountId((string) $options['discount_id']);
        }

        if (isset($options['amount']) && is_numeric($options['amount'])) {
            $checkout->withAmount((int) $options['amount']);
        }

        return $checkout;
    }

    /**
     * Create a new checkout instance to sell a product with a custom price.
     *
     * @param  array<string>  $products
     * @param  array<string, string|int>|null  $options
     * @param  array<string, string|int|bool>|null  $customerMetadata
     * @param  array<string, string|int|bool>|null  $metadata
     */
    public function charge(int $amount, array $products, ?array $options = [], ?array $customerMetadata = [], ?array $metadata = []): Checkout
    {
        return $this->checkout($products, array_merge($options, [
            'amount' => $amount,
        ]), $customerMetadata, $metadata);
    }

    /**
     * Subscribe the customer to a new plan variant.
     *
     * @param  array<string, string|int>|null  $options
     * @param  array<string, string|int|bool>|null  $customerMetadata
     * @param  array<string, string|int|bool>|null  $metadata
     */
    public function subscribe(string $productId, string $type = "default", ?array $options = [], ?array $customerMetadata = [], ?array $metadata = []): Checkout
    {
        return $this->checkout([$productId], $options, array_merge($customerMetadata, [
            'subscription_type' => $type,
        ]), $metadata);
    }
}

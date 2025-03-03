<?php

namespace Danestves\LaravelPolar\Contracts;

use Danestves\LaravelPolar\Checkout;
use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Danestves\LaravelPolar\Order;
use Danestves\LaravelPolar\Subscription;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\RedirectResponse;

interface Billable
{
    /**
     * Create a new checkout instance to sell a product with a custom price.
     *
     * @param  array<string>  $products
     * @param  array<string, string|int>  $options
     * @param  array<string, string|int|bool>  $customerMetadata
     */
    public function charge(int $amount, array $products, array $options = [], array $customerMetadata = []): Checkout;

    /**
     * Create a new checkout instance to sell a product.
     *
     * @param  array<string>  $products
     * @param  array<string, string|int>  $options
     * @param  array<string, string|int|bool>  $customerMetadata
     */
    public function checkout(array $products, array $options = [], array $customerMetadata = []): Checkout;

    /**
     * Create a customer record for the billable model.
     *
     * @param  array<string, string|int>  $attributes
     */
    public function createAsCustomer(array $attributes = []): Customer;

    /**
     * Get the customer related to the billable model.
     *
     * @return MorphOne<Customer, \Illuminate\Database\Eloquent\Model>
     */
    public function customer(): MorphOne;

    /**
     * Get the billable's name to associate with Polar.
     */
    public function polarName(): ?string;

    /**
     * Get the billable's email address to associate with Polar.
     */
    public function polarEmail(): ?string;

    /**
     * Generate a redirect response to the billable's customer portal.
     */
    public function redirectToCustomerPortal(): RedirectResponse;

    /**
     * Get the customer portal url for this billable.
     *
     * @throws PolarApiError
     * @throws InvalidCustomer
     */
    public function customerPortalUrl(): string;

    /**
     * Get all of the orders for the billable.
     *
     * @return MorphMany<Order, \Illuminate\Database\Eloquent\Model>
     */
    public function orders(): MorphMany;

    /**
     * Determine if the billable has purchased a specific product.
     */
    public function hasPurchasedProduct(string $productId): bool;

    /**
     * Determine if the billable has purchased a specific price of a product.
     */
    public function hasPurchasedPrice(string $productPriceId): bool;

    /**
     * Get all of the subscriptions for the billable.
     *
     * @return MorphMany<Subscription, \Illuminate\Database\Eloquent\Model>
     */
    public function subscriptions(): MorphMany;

    /**
     * Get a subscription instance by type.
     */
    public function subscription(string $type = 'default'): ?Subscription;

    /**
     * Determine if the billable has a valid subscription.
     */
    public function subscribed(string $type = 'default', ?string $priceId = null): bool;

    /**
     * Determine if the billable has a valid subscription to a specific price.
     */
    public function subscribedToPrice(string $priceId, string $type = 'default'): bool;
}

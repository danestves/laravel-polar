<?php

namespace Danestves\LaravelPolar;

use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Illuminate\Contracts\Container\BindingResolutionException;
use Polar\Models\Components;
use Polar\Models\Errors;
use Polar\Models\Operations;
use Polar\Polar;

class LaravelPolar
{
    /**
     * The customer model class name.
     */
    public static string $customerModel = Customer::class;

    /**
     * The subscription model class name.
     */
    public static string $subscriptionModel = Subscription::class;

    /**
     * The order model class name.
     */
    public static string $orderModel = Order::class;

    /**
     * Create a checkout session.
     *
     * @throws PolarApiError
     */
    public static function createCheckoutSession(Components\CheckoutProductsCreate $request): ?Components\Checkout
    {
        try {
            $responses = self::sdk()->checkouts->create(request: $request);

            return $responses->checkout;
        } catch (Errors\APIException $e) {
            throw new PolarApiError($e->getMessage(), 400);
        }
    }

    /**
     * Update a subscription.
     *
     * @throws PolarApiError
     */
    public static function updateSubscription(string $subscriptionId, Components\SubscriptionUpdateProduct|Components\SubscriptionCancel $request): ?Components\Subscription
    {
        try {
            $responses = self::sdk()->subscriptions->update(id: $subscriptionId, subscriptionUpdate: $request);

            return $responses->subscription;
        } catch (Errors\APIException $e) {
            throw new PolarApiError($e->getMessage(), 400);
        }
    }

    /**
     * List all products.
     *
     * @throws PolarApiError
     */
    public static function listProducts(Operations\ProductsListRequest $request): ?Components\ListResourceProduct
    {
        try {
            $responses = self::sdk()->products->list(request: $request);

            foreach ($responses as $response) {
                if ($response->statusCode === 200) {
                    return $response->listResourceProduct;
                }
            }

            return null;
        } catch (Errors\APIException $e) {
            throw new PolarApiError($e->getMessage(), 400);
        }
    }

    /**
     * Create a customer session.
     *
     * @throws PolarApiError
     */
    public static function createCustomerSession(Components\CustomerSessionCustomerIDCreate $request): ?Components\CustomerSession
    {
        try {
            $responses = self::sdk()->customerSessions->create(request: $request);

            return $responses->customerSession;
        } catch (Errors\APIException $e) {
            throw new PolarApiError($e->getMessage(), 400);
        }
    }

    /**
     * Get the Polar SDK instance.
     *
     * @throws BindingResolutionException
     */
    private static function sdk(): Polar
    {
        return Polar::builder()
            ->setSecurity(config('polar.access_token'))
            ->setServer(app()->environment('production') ? 'production' : 'sandbox')
            ->build();
    }

    /**
     * Set the customer model class name.
     */
    public static function useCustomerModel(string $customerModel): void
    {
        static::$customerModel = $customerModel;
    }

    /**
     * Set the subscription model class name.
     */
    public static function useSubscriptionModel(string $subscriptionModel): void
    {
        static::$subscriptionModel = $subscriptionModel;
    }

    /**
     * Set the order model class name.
     */
    public static function useOrderModel(string $orderModel): void
    {
        static::$orderModel = $orderModel;
    }
}

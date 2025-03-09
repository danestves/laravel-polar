<?php

namespace Danestves\LaravelPolar;

use Danestves\LaravelPolar\Data\Checkout\CheckoutSessionData;
use Danestves\LaravelPolar\Data\Checkout\CreateCheckoutSessionData;
use Danestves\LaravelPolar\Data\Products\ListProductsData;
use Danestves\LaravelPolar\Data\Products\ListProductsRequestData;
use Danestves\LaravelPolar\Data\Sessions\CustomerSessionCustomerExternalIDCreateData;
use Danestves\LaravelPolar\Data\Sessions\CustomerSessionCustomerIDCreateData;
use Danestves\LaravelPolar\Data\Sessions\CustomerSessionData;
use Danestves\LaravelPolar\Data\Subscriptions\SubscriptionCancelData;
use Danestves\LaravelPolar\Data\Subscriptions\SubscriptionData;
use Danestves\LaravelPolar\Data\Subscriptions\SubscriptionUpdateProductData;
use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Exception;
use Http;
use Illuminate\Http\Client\Response;

class LaravelPolar
{
    public const string VERSION = '0.3.2';

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
    public static function createCheckoutSession(CreateCheckoutSessionData $request): ?CheckoutSessionData
    {
        try {
            $response = self::api("POST", "v1/checkouts", $request->toArray());

            return CheckoutSessionData::from($response->json());
        } catch (PolarApiError $e) {
            throw new PolarApiError($e->getMessage(), 400);
        }
    }

    /**
     * Update a subscription.
     *
     * @throws PolarApiError
     */
    public static function updateSubscription(string $subscriptionId, SubscriptionUpdateProductData|SubscriptionCancelData $request): SubscriptionData
    {
        try {
            $response = self::api("POST", "v1/subscriptions/$subscriptionId", $request->toArray());

            return SubscriptionData::from($response->json());
        } catch (PolarApiError $e) {
            throw new PolarApiError($e->getMessage(), 400);
        }
    }

    /**
     * List all products.
     *
     * @throws PolarApiError
     */
    public static function listProducts(?ListProductsRequestData $request): ListProductsData
    {
        try {
            $response = self::api("GET", "v1/products", $request->toArray());

            return ListProductsData::from($response->json());
        } catch (PolarApiError $e) {
            throw new PolarApiError($e->getMessage(), 400);
        }
    }

    /**
     * Create a customer session.
     *
     * @throws PolarApiError
     */
    public static function createCustomerSession(CustomerSessionCustomerIDCreateData|CustomerSessionCustomerExternalIDCreateData $request): CustomerSessionData
    {
        try {
            $response = self::api("POST", "v1/customer-sessions", $request->toArray());

            return CustomerSessionData::from($response->json());
        } catch (PolarApiError $e) {
            throw new PolarApiError($e->getMessage(), 400);
        }
    }

    /**
     * Perform a Polar API call.
     *
     * @param array<string, mixed> $payload The payload to send to the API.
     *
     * @throws Exception
     * @throws PolarApiError
     */
    public static function api(string $method, string $uri, array $payload = []): Response
    {
        if (empty($apiKey = config('polar.access_token'))) {
            throw new Exception('Polar API key not set.');
        }

        $api = app()->environment('production') ? 'https://api.polar.sh' : 'https://sandbox-api.polar.sh';

        $response = Http::withToken($apiKey)
                    ->withUserAgent('Danestves\LaravelPolar/' . static::VERSION)
                    ->accept('application/vnd.api+json')
                    ->contentType('application/vnd.api+json')
            ->$method("$api/$uri", $payload);

        if ($response->failed()) {
            throw new PolarApiError(json_encode($response['detail']), 422);
        }

        return $response;
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

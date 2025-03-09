<?php

namespace Danestves\LaravelPolar;

use Danestves\LaravelPolar\Data\Checkout\CheckoutSessionData;
use Danestves\LaravelPolar\Data\Checkout\CreateCheckoutSessionData;
use Danestves\LaravelPolar\Data\Subscriptions\SubscriptionCancelData;
use Danestves\LaravelPolar\Data\Subscriptions\SubscriptionData;
use Danestves\LaravelPolar\Data\Subscriptions\SubscriptionUpdateProductData;
use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Exception;
use Http;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Response;
use Polar\Models\Components;
use Polar\Models\Errors;
use Polar\Models\Operations;
use Polar\Polar;

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
        } catch (Errors\APIException $e) {
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
    public static function createCustomerSession(Components\CustomerSessionCustomerIDCreate $request): null
    {
        try {
            $responses = self::api("POST", "v1/checkouts");

            return null;
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
            throw new PolarApiError($response['errors'][0]['detail'], (int) $response['errors'][0]['status']);
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

<?php

namespace Danestves\LaravelPolar;

use Exception;
use Polar\Models\Components;
use Polar\Models\Errors;
use Polar\Models\Operations;
use Polar\Polar;

class LaravelPolar
{
    public const string VERSION = '2.5.0';

    /**
     * The cached Polar SDK instance.
     */
    private static ?Polar $sdkInstance = null;

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
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function createCheckoutSession(Components\CheckoutCreate $request): Components\Checkout
    {
        $sdk = self::sdk();

        $response = $sdk->checkouts->create(request: $request);

        if ($response->statusCode === 201 && $response->checkout !== null) {
            return $response->checkout;
        }

        throw new Errors\APIException('Failed to create checkout session', $response->statusCode ?? 500, '', null);
    }

    /**
     * Update a subscription.
     *
     * @param Components\SubscriptionUpdateProduct|Components\SubscriptionCancel|Components\SubscriptionUpdateDiscount|Components\SubscriptionUpdateTrial|Components\SubscriptionUpdateSeats|Components\SubscriptionRevoke $request
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function updateSubscription(string $subscriptionId, Components\SubscriptionUpdateProduct|Components\SubscriptionCancel|Components\SubscriptionUpdateDiscount|Components\SubscriptionUpdateTrial|Components\SubscriptionUpdateSeats|Components\SubscriptionRevoke $request): Components\Subscription
    {
        $sdk = self::sdk();

        $response = $sdk->subscriptions->update(
            id: $subscriptionId,
            subscriptionUpdate: $request,
        );

        if ($response->statusCode === 200 && $response->subscription !== null) {
            return $response->subscription;
        }

        throw new Errors\APIException('Failed to update subscription', 500, '', null);
    }

    /**
     * List all products.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function listProducts(?Operations\ProductsListRequest $request = null): Operations\ProductsListResponse
    {
        $sdk = self::sdk();

        if ($request === null) {
            $request = new Operations\ProductsListRequest();
        }

        $generator = $sdk->products->list(request: $request);

        foreach ($generator as $response) {
            if ($response->statusCode === 200) {
                return $response;
            }
        }

        throw new Errors\APIException('Failed to list products', 500, '', null);
    }

    /**
     * Create a customer session.
     *
     * @param Components\CustomerSessionCustomerIDCreate|Components\CustomerSessionCustomerExternalIDCreate $request
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function createCustomerSession(Components\CustomerSessionCustomerIDCreate|Components\CustomerSessionCustomerExternalIDCreate $request): Components\CustomerSession
    {
        $sdk = self::sdk();

        $response = $sdk->customerSessions->create(request: $request);

        if ($response->statusCode === 201 && $response->customerSession !== null) {
            return $response->customerSession;
        }

        throw new Errors\APIException('Failed to create customer session', 500, '', null);
    }

    /**
     * Create a benefit.
     *
     * @param Components\BenefitCustomCreate|Components\BenefitDiscordCreate|Components\BenefitGitHubRepositoryCreate|Components\BenefitDownloadablesCreate|Components\BenefitLicenseKeysCreate|Components\BenefitMeterCreditCreate $request
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function createBenefit(Components\BenefitCustomCreate|Components\BenefitDiscordCreate|Components\BenefitGitHubRepositoryCreate|Components\BenefitDownloadablesCreate|Components\BenefitLicenseKeysCreate|Components\BenefitMeterCreditCreate $request): Components\BenefitCustom|Components\BenefitDiscord|Components\BenefitGitHubRepository|Components\BenefitDownloadables|Components\BenefitLicenseKeys|Components\BenefitMeterCredit
    {
        $sdk = self::sdk();

        $response = $sdk->benefits->create(request: $request);

        if ($response->statusCode === 201 && $response->benefit !== null) {
            return $response->benefit;
        }

        throw new Errors\APIException('Failed to create benefit', 500, '', null);
    }

    /**
     * Update a benefit.
     *
     * @param Components\BenefitCustomUpdate|Components\BenefitDiscordUpdate|Components\BenefitGitHubRepositoryUpdate|Components\BenefitDownloadablesUpdate|Components\BenefitLicenseKeysUpdate|Components\BenefitMeterCreditUpdate $request
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function updateBenefit(string $benefitId, Components\BenefitCustomUpdate|Components\BenefitDiscordUpdate|Components\BenefitGitHubRepositoryUpdate|Components\BenefitDownloadablesUpdate|Components\BenefitLicenseKeysUpdate|Components\BenefitMeterCreditUpdate $request): Components\BenefitCustom|Components\BenefitDiscord|Components\BenefitGitHubRepository|Components\BenefitDownloadables|Components\BenefitLicenseKeys|Components\BenefitMeterCredit
    {
        $sdk = self::sdk();

        $response = $sdk->benefits->update(id: $benefitId, requestBody: $request);

        if ($response->statusCode === 200 && $response->benefit !== null) {
            return $response->benefit;
        }

        throw new Errors\APIException('Failed to update benefit', 500, '', null);
    }

    /**
     * Delete a benefit.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function deleteBenefit(string $benefitId): void
    {
        $sdk = self::sdk();

        $response = $sdk->benefits->delete(id: $benefitId);

        if ($response->statusCode !== 200 && $response->statusCode !== 204) {
            throw new Errors\APIException('Failed to delete benefit', 500, '', null);
        }
    }

    /**
     * List all benefits.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function listBenefits(Operations\BenefitsListRequest $request): Operations\BenefitsListResponse
    {
        $sdk = self::sdk();

        $generator = $sdk->benefits->list(request: $request);

        foreach ($generator as $response) {
            if ($response->statusCode === 200) {
                return $response;
            }
        }

        throw new Errors\APIException('Failed to list benefits', 500, '', null);
    }

    /**
     * Get a specific benefit by ID.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function getBenefit(string $benefitId): Components\BenefitCustom|Components\BenefitDiscord|Components\BenefitGitHubRepository|Components\BenefitDownloadables|Components\BenefitLicenseKeys|Components\BenefitMeterCredit
    {
        $sdk = self::sdk();

        $response = $sdk->benefits->get(id: $benefitId);

        if ($response->statusCode === 200 && $response->benefit !== null) {
            return $response->benefit;
        }

        throw new Errors\APIException('Failed to get benefit', 500, '', null);
    }

    /**
     * List all grants for a specific benefit.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function listBenefitGrants(Operations\BenefitsGrantsRequest $request): Operations\BenefitsGrantsResponse
    {
        $sdk = self::sdk();

        $generator = $sdk->benefits->grants(request: $request);

        foreach ($generator as $response) {
            if ($response->statusCode === 200) {
                return $response;
            }
        }

        throw new Errors\APIException('Failed to list benefit grants', 500, '', null);
    }

    /**
     * Ingest usage events for metered billing.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function ingestEvents(Components\EventsIngest $request): void
    {
        $sdk = self::sdk();

        $response = $sdk->events->ingest(request: $request);

        if ($response->statusCode !== 202) {
            throw new Errors\APIException('Failed to ingest events', 500, '', null);
        }
    }

    /**
     * List customer meters.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function listCustomerMeters(Operations\CustomerMetersListRequest $request): Operations\CustomerMetersListResponse
    {
        $sdk = self::sdk();

        $generator = $sdk->customerMeters->list(request: $request);

        foreach ($generator as $response) {
            if ($response->statusCode === 200) {
                return $response;
            }
        }

        throw new Errors\APIException('Failed to list customer meters', 500, '', null);
    }

    /**
     * Get a specific customer meter by ID.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function getCustomerMeter(string $meterId): Components\CustomerMeter
    {
        $sdk = self::sdk();

        $response = $sdk->customerMeters->get(id: $meterId);

        if ($response->statusCode === 200 && $response->customerMeter !== null) {
            return $response->customerMeter;
        }

        throw new Errors\APIException('Failed to get customer meter', 500, '', null);
    }

    /**
     * Create a discount.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function createDiscount(Components\DiscountFixedOnceForeverDurationCreate|Components\DiscountFixedRepeatDurationCreate|Components\DiscountPercentageOnceForeverDurationCreate|Components\DiscountPercentageRepeatDurationCreate $request): Components\DiscountFixedOnceForeverDuration|Components\DiscountFixedRepeatDuration|Components\DiscountPercentageOnceForeverDuration|Components\DiscountPercentageRepeatDuration
    {
        $sdk = self::sdk();

        $response = $sdk->discounts->create(request: $request);

        if ($response->statusCode === 201 && $response->discount !== null) {
            return $response->discount;
        }

        throw new Errors\APIException('Failed to create discount', $response->statusCode ?? 500, '', null);
    }

    /**
     * Update a discount.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function updateDiscount(string $discountId, Components\DiscountUpdate $request): Components\DiscountFixedOnceForeverDuration|Components\DiscountFixedRepeatDuration|Components\DiscountPercentageOnceForeverDuration|Components\DiscountPercentageRepeatDuration
    {
        $sdk = self::sdk();

        $response = $sdk->discounts->update(discountUpdate: $request, id: $discountId);

        if ($response->statusCode === 200 && $response->discount !== null) {
            return $response->discount;
        }

        throw new Errors\APIException('Failed to update discount', $response->statusCode ?? 500, '', null);
    }

    /**
     * Delete a discount.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function deleteDiscount(string $discountId): void
    {
        $sdk = self::sdk();

        $response = $sdk->discounts->delete(id: $discountId);

        if ($response->statusCode !== 200 && $response->statusCode !== 204) {
            throw new Errors\APIException('Failed to delete discount', $response->statusCode, '', null);
        }
    }

    /**
     * List discounts.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function listDiscounts(?Operations\DiscountsListRequest $request = null): Operations\DiscountsListResponse
    {
        $sdk = self::sdk();

        if ($request === null) {
            $request = new Operations\DiscountsListRequest();
        }

        $generator = $sdk->discounts->list(request: $request);

        foreach ($generator as $response) {
            if ($response->statusCode === 200) {
                return $response;
            }
        }

        throw new Errors\APIException('Failed to list discounts', 500, '', null);
    }

    /**
     * Get a specific discount by ID.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function getDiscount(string $discountId): Components\DiscountFixedOnceForeverDuration|Components\DiscountFixedRepeatDuration|Components\DiscountPercentageOnceForeverDuration|Components\DiscountPercentageRepeatDuration
    {
        $sdk = self::sdk();

        $response = $sdk->discounts->get(id: $discountId);

        if ($response->statusCode === 200 && $response->discount !== null) {
            return $response->discount;
        }

        throw new Errors\APIException('Failed to get discount', $response->statusCode ?? 500, '', null);
    }

    /**
     * Create a refund for an order.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function createRefund(Components\RefundCreate $request): Components\Refund
    {
        $sdk = self::sdk();

        $response = $sdk->refunds->create(request: $request);

        if ($response->statusCode === 200 && $response->refund !== null) {
            return $response->refund;
        }

        throw new Errors\APIException('Failed to create refund', $response->statusCode ?? 500, '', null);
    }

    /**
     * List refunds.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function listRefunds(?Operations\RefundsListRequest $request = null): Operations\RefundsListResponse
    {
        $sdk = self::sdk();

        if ($request === null) {
            $request = new Operations\RefundsListRequest();
        }

        $generator = $sdk->refunds->list(request: $request);

        foreach ($generator as $response) {
            if ($response->statusCode === 200) {
                return $response;
            }
        }

        throw new Errors\APIException('Failed to list refunds', 500, '', null);
    }

    /**
     * Reset the cached SDK instance (useful for testing).
     */
    public static function resetSdk(): void
    {
        self::$sdkInstance = null;
    }

    /**
     * Set the SDK instance (useful for testing).
     */
    public static function setSdk(?Polar $sdk): void
    {
        self::$sdkInstance = $sdk;
    }

    /**
     * Get or create a cached Polar SDK instance.
     *
     * @throws Exception
     */
    public static function sdk(): Polar
    {
        if (self::$sdkInstance !== null) {
            return self::$sdkInstance;
        }

        if (empty($apiKey = config('polar.access_token'))) {
            throw new Exception('Polar API key not set.');
        }

        self::$sdkInstance = Polar::builder()
            ->setSecurity($apiKey)
            ->setServer(config('polar.server', 'sandbox'))
            ->build();

        return self::$sdkInstance;
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

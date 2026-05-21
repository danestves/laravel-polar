<?php

namespace Danestves\LaravelPolar;

use Exception;
use Polar\Models\Components;
use Polar\Models\Errors;
use Polar\Models\Operations;
use Polar\Polar;

class LaravelPolar
{
    public const string VERSION = '2.11.0';

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
     * List the seats on a subscription or order.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function listSeats(?string $subscriptionId = null, ?string $orderId = null): Components\SeatsList
    {
        $sdk = self::sdk();

        $response = $sdk->customerSeats->listSeats(subscriptionId: $subscriptionId, orderId: $orderId);

        if ($response->statusCode === 200 && $response->seatsList !== null) {
            return $response->seatsList;
        }

        throw new Errors\APIException('Failed to list seats', $response->statusCode ?? 500, '', null);
    }

    /**
     * Assign a seat to a member by email, customer id, or external id.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function assignSeat(Components\SeatAssign $request): Components\CustomerSeat
    {
        $sdk = self::sdk();

        $response = $sdk->customerSeats->assignSeat(request: $request);

        if ($response->statusCode === 200 && $response->customerSeat !== null) {
            return $response->customerSeat;
        }

        throw new Errors\APIException('Failed to assign seat', $response->statusCode ?? 500, '', null);
    }

    /**
     * Revoke a seat from a member.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function revokeSeat(string $seatId): Components\CustomerSeat
    {
        $sdk = self::sdk();

        $response = $sdk->customerSeats->revokeSeat(seatId: $seatId);

        if ($response->statusCode === 200 && $response->customerSeat !== null) {
            return $response->customerSeat;
        }

        throw new Errors\APIException('Failed to revoke seat', $response->statusCode ?? 500, '', null);
    }

    /**
     * Resend the invitation email for a pending seat.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function resendSeatInvitation(string $seatId): Components\CustomerSeat
    {
        $sdk = self::sdk();

        $response = $sdk->customerSeats->resendInvitation(seatId: $seatId);

        if ($response->statusCode === 200 && $response->customerSeat !== null) {
            return $response->customerSeat;
        }

        throw new Errors\APIException('Failed to resend seat invitation', $response->statusCode ?? 500, '', null);
    }

    /**
     * List license keys (admin-scoped, requires an org-scoped access token).
     *
     * @param  string|array<string>|null  $organizationId
     * @param  string|array<string>|null  $benefitId
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function listLicenseKeys(string|array|null $organizationId = null, string|array|null $benefitId = null, ?int $page = null, ?int $limit = null): Operations\LicenseKeysListResponse
    {
        $sdk = self::sdk();

        $generator = $sdk->licenseKeys->list(
            organizationId: $organizationId,
            benefitId: $benefitId,
            page: $page,
            limit: $limit,
        );

        foreach ($generator as $response) {
            if ($response->statusCode === 200) {
                return $response;
            }
        }

        throw new Errors\APIException('Failed to list license keys', 500, '', null);
    }

    /**
     * Get a license key by ID (admin-scoped).
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function getLicenseKey(string $licenseKeyId): Components\LicenseKeyWithActivations
    {
        $sdk = self::sdk();

        $response = $sdk->licenseKeys->get(id: $licenseKeyId);

        if ($response->statusCode === 200 && $response->licenseKeyWithActivations !== null) {
            return $response->licenseKeyWithActivations;
        }

        throw new Errors\APIException('Failed to get license key', $response->statusCode ?? 500, '', null);
    }

    /**
     * Update a license key (admin-scoped).
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function updateLicenseKey(string $licenseKeyId, Components\LicenseKeyUpdate $request): Components\LicenseKeyRead
    {
        $sdk = self::sdk();

        $response = $sdk->licenseKeys->update(licenseKeyUpdate: $request, id: $licenseKeyId);

        if ($response->statusCode === 200 && $response->licenseKeyRead !== null) {
            return $response->licenseKeyRead;
        }

        throw new Errors\APIException('Failed to update license key', $response->statusCode ?? 500, '', null);
    }

    /**
     * Validate a license key. Public-facing — does not require an org-scoped
     * access token but does require an organization id (passed as arg or
     * configured via polar.organization_id).
     *
     * @param  array<string, mixed>|null  $conditions
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function validateLicenseKey(string $key, ?string $organizationId = null, ?string $activationId = null, ?array $conditions = null, ?string $benefitId = null, ?string $customerId = null, ?int $incrementUsage = null): Components\ValidatedLicenseKey
    {
        $sdk = self::sdk();

        $request = new Components\LicenseKeyValidate(
            key: $key,
            organizationId: self::resolveOrganizationId($organizationId),
            conditions: $conditions,
            activationId: $activationId,
            benefitId: $benefitId,
            customerId: $customerId,
            incrementUsage: $incrementUsage,
        );

        $response = $sdk->licenseKeys->validate(request: $request);

        if ($response->statusCode === 200 && $response->validatedLicenseKey !== null) {
            return $response->validatedLicenseKey;
        }

        throw new Errors\APIException('Failed to validate license key', $response->statusCode ?? 500, '', null);
    }

    /**
     * Activate a license key. Public-facing — does not require an org-scoped
     * access token but does require an organization id (passed as arg or
     * configured via polar.organization_id).
     *
     * @param  array<string, mixed>|null  $conditions
     * @param  array<string, mixed>|null  $meta
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function activateLicenseKey(string $key, string $label, ?string $organizationId = null, ?array $conditions = null, ?array $meta = null): Components\LicenseKeyActivationRead
    {
        $sdk = self::sdk();

        $request = new Components\LicenseKeyActivate(
            key: $key,
            organizationId: self::resolveOrganizationId($organizationId),
            label: $label,
            conditions: $conditions,
            meta: $meta,
        );

        $response = $sdk->licenseKeys->activate(request: $request);

        if ($response->statusCode === 200 && $response->licenseKeyActivationRead !== null) {
            return $response->licenseKeyActivationRead;
        }

        throw new Errors\APIException('Failed to activate license key', $response->statusCode ?? 500, '', null);
    }

    /**
     * Deactivate a license key activation. Public-facing — same auth pattern
     * as activate/validate.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function deactivateLicenseKey(string $key, string $activationId, ?string $organizationId = null): void
    {
        $sdk = self::sdk();

        $request = new Components\LicenseKeyDeactivate(
            key: $key,
            organizationId: self::resolveOrganizationId($organizationId),
            activationId: $activationId,
        );

        $response = $sdk->licenseKeys->deactivate(request: $request);

        if ($response->statusCode !== 200 && $response->statusCode !== 204) {
            throw new Errors\APIException('Failed to deactivate license key', $response->statusCode, '', null);
        }
    }

    /**
     * Resolve an organization id from an explicit argument or the config
     * fallback. Throws when neither is set.
     */
    private static function resolveOrganizationId(?string $organizationId): string
    {
        $resolved = $organizationId ?? config('polar.organization_id');

        if (! is_string($resolved) || $resolved === '') {
            throw new \InvalidArgumentException('Polar organization id is required. Pass it explicitly or set polar.organization_id in your config.');
        }

        return $resolved;
    }

    /**
     * Create a custom field.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function createCustomField(Components\CustomFieldCreateText|Components\CustomFieldCreateNumber|Components\CustomFieldCreateDate|Components\CustomFieldCreateCheckbox|Components\CustomFieldCreateSelect $request): Components\CustomFieldText|Components\CustomFieldNumber|Components\CustomFieldDate|Components\CustomFieldCheckbox|Components\CustomFieldSelect
    {
        $sdk = self::sdk();

        $response = $sdk->customFields->create(request: $request);

        if ($response->statusCode === 201 && $response->customField !== null) {
            return $response->customField;
        }

        throw new Errors\APIException('Failed to create custom field', $response->statusCode ?? 500, '', null);
    }

    /**
     * Update a custom field.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function updateCustomField(string $customFieldId, Components\CustomFieldUpdateText|Components\CustomFieldUpdateNumber|Components\CustomFieldUpdateDate|Components\CustomFieldUpdateCheckbox|Components\CustomFieldUpdateSelect $request): Components\CustomFieldText|Components\CustomFieldNumber|Components\CustomFieldDate|Components\CustomFieldCheckbox|Components\CustomFieldSelect
    {
        $sdk = self::sdk();

        $response = $sdk->customFields->update(customFieldUpdate: $request, id: $customFieldId);

        if ($response->statusCode === 200 && $response->customField !== null) {
            return $response->customField;
        }

        throw new Errors\APIException('Failed to update custom field', $response->statusCode ?? 500, '', null);
    }

    /**
     * Delete a custom field.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function deleteCustomField(string $customFieldId): void
    {
        $sdk = self::sdk();

        $response = $sdk->customFields->delete(id: $customFieldId);

        if ($response->statusCode !== 200 && $response->statusCode !== 204) {
            throw new Errors\APIException('Failed to delete custom field', $response->statusCode, '', null);
        }
    }

    /**
     * List custom fields.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function listCustomFields(?Operations\CustomFieldsListRequest $request = null): Operations\CustomFieldsListResponse
    {
        $sdk = self::sdk();

        if ($request === null) {
            $request = new Operations\CustomFieldsListRequest();
        }

        $generator = $sdk->customFields->list(request: $request);

        foreach ($generator as $response) {
            if ($response->statusCode === 200) {
                return $response;
            }
        }

        throw new Errors\APIException('Failed to list custom fields', 500, '', null);
    }

    /**
     * Get a specific custom field by ID.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function getCustomField(string $customFieldId): Components\CustomFieldText|Components\CustomFieldNumber|Components\CustomFieldDate|Components\CustomFieldCheckbox|Components\CustomFieldSelect
    {
        $sdk = self::sdk();

        $response = $sdk->customFields->get(id: $customFieldId);

        if ($response->statusCode === 200 && $response->customField !== null) {
            return $response->customField;
        }

        throw new Errors\APIException('Failed to get custom field', $response->statusCode ?? 500, '', null);
    }

    /**
     * Create a checkout link.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function createCheckoutLink(Components\CheckoutLinkCreateProductPrice|Components\CheckoutLinkCreateProduct|Components\CheckoutLinkCreateProducts $request): Components\CheckoutLink
    {
        $sdk = self::sdk();

        $response = $sdk->checkoutLinks->create(request: $request);

        if ($response->statusCode === 201 && $response->checkoutLink !== null) {
            return $response->checkoutLink;
        }

        throw new Errors\APIException('Failed to create checkout link', $response->statusCode ?? 500, '', null);
    }

    /**
     * Update a checkout link.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function updateCheckoutLink(string $checkoutLinkId, Components\CheckoutLinkUpdate $request): Components\CheckoutLink
    {
        $sdk = self::sdk();

        $response = $sdk->checkoutLinks->update(checkoutLinkUpdate: $request, id: $checkoutLinkId);

        if ($response->statusCode === 200 && $response->checkoutLink !== null) {
            return $response->checkoutLink;
        }

        throw new Errors\APIException('Failed to update checkout link', $response->statusCode ?? 500, '', null);
    }

    /**
     * Delete a checkout link.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function deleteCheckoutLink(string $checkoutLinkId): void
    {
        $sdk = self::sdk();

        $response = $sdk->checkoutLinks->delete(id: $checkoutLinkId);

        if ($response->statusCode !== 200 && $response->statusCode !== 204) {
            throw new Errors\APIException('Failed to delete checkout link', $response->statusCode, '', null);
        }
    }

    /**
     * List checkout links.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function listCheckoutLinks(?Operations\CheckoutLinksListRequest $request = null): Operations\CheckoutLinksListResponse
    {
        $sdk = self::sdk();

        if ($request === null) {
            $request = new Operations\CheckoutLinksListRequest();
        }

        $generator = $sdk->checkoutLinks->list(request: $request);

        foreach ($generator as $response) {
            if ($response->statusCode === 200) {
                return $response;
            }
        }

        throw new Errors\APIException('Failed to list checkout links', 500, '', null);
    }

    /**
     * Get a specific checkout link by ID.
     *
     * @throws Errors\APIException
     * @throws Exception
     */
    public static function getCheckoutLink(string $checkoutLinkId): Components\CheckoutLink
    {
        $sdk = self::sdk();

        $response = $sdk->checkoutLinks->get(id: $checkoutLinkId);

        if ($response->statusCode === 200 && $response->checkoutLink !== null) {
            return $response->checkoutLink;
        }

        throw new Errors\APIException('Failed to get checkout link', $response->statusCode ?? 500, '', null);
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

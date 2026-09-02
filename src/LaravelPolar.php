<?php

namespace Danestves\LaravelPolar;

use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Danestves\LaravelPolar\Http\Page;
use Danestves\LaravelPolar\Http\PolarClient;
use Exception;
use Spatie\LaravelData\Data as SpatieData;

class LaravelPolar
{
    public const string VERSION = '3.1.0';

    /**
     * The cached HTTP client.
     */
    private static ?PolarClient $client = null;

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

    // -- checkouts ----------------------------------------------------------

    /**
     * Create a checkout session.
     *
     * @param  Data\CheckoutCreate|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function createCheckoutSession(Data\CheckoutCreate|array $request): Data\Checkout
    {
        return Data\Checkout::from(self::client()->post('/v1/checkouts/', $request));
    }

    // -- subscriptions ------------------------------------------------------

    /**
     * Update a subscription.
     *
     * Polar treats an absent key as "leave unchanged" and an explicit null as "clear", so pass
     * `$keepNulls` when you mean to clear a field (removing a discount, for instance).
     *
     * @param  Data\SubscriptionUpdate|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function updateSubscription(string $subscriptionId, Data\SubscriptionUpdate|array $request, bool $keepNulls = false): Data\Subscription
    {
        return Data\Subscription::from(self::client()->patch(
            '/v1/subscriptions/' . urlencode($subscriptionId),
            $request,
            keepNulls: $keepNulls,
        ));
    }

    // -- products -----------------------------------------------------------

    /**
     * List products.
     *
     * @param  array<string, mixed>  $query
     * @return Page<Data\Product>
     *
     * @throws PolarApiError
     */
    public static function listProducts(array $query = []): Page
    {
        return self::client()->page('/v1/products/', Data\Product::class, $query);
    }

    // -- customer sessions --------------------------------------------------

    /**
     * Create a customer session, used to authenticate against the customer portal API.
     *
     * @param  Data\CustomerSessionCustomerIDCreate|Data\CustomerSessionCustomerExternalIDCreate|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function createCustomerSession(
        Data\CustomerSessionCustomerIDCreate|Data\CustomerSessionCustomerExternalIDCreate|array $request,
    ): Data\CustomerSession {
        return Data\CustomerSession::from(self::client()->post('/v1/customer-sessions/', $request));
    }

    // -- benefits -----------------------------------------------------------

    /**
     * Create a benefit.
     *
     * @param  Data\BenefitCreate|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function createBenefit(Data\BenefitCreate|array $request): Data\Benefit
    {
        return Data\Benefit::from(self::client()->post('/v1/benefits/', $request));
    }

    /**
     * Update a benefit.
     *
     * @param  SpatieData|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function updateBenefit(string $benefitId, SpatieData|array $request): Data\Benefit
    {
        return Data\Benefit::from(self::client()->patch('/v1/benefits/' . urlencode($benefitId), $request));
    }

    /**
     * Delete a benefit.
     *
     * @throws PolarApiError
     */
    public static function deleteBenefit(string $benefitId): void
    {
        self::client()->delete('/v1/benefits/' . urlencode($benefitId));
    }

    /**
     * List benefits.
     *
     * @param  array<string, mixed>  $query
     * @return Page<Data\Benefit>
     *
     * @throws PolarApiError
     */
    public static function listBenefits(array $query = []): Page
    {
        return self::client()->page('/v1/benefits/', Data\Benefit::class, $query);
    }

    /**
     * Get a specific benefit by ID.
     *
     * @throws PolarApiError
     */
    public static function getBenefit(string $benefitId): Data\Benefit
    {
        return Data\Benefit::from(self::client()->get('/v1/benefits/' . urlencode($benefitId)));
    }

    /**
     * List all grants for a specific benefit.
     *
     * @param  array<string, mixed>  $query
     * @return Page<Data\BenefitGrant>
     *
     * @throws PolarApiError
     */
    public static function listBenefitGrants(string $benefitId, array $query = []): Page
    {
        return self::client()->page(
            '/v1/benefits/' . urlencode($benefitId) . '/grants',
            Data\BenefitGrant::class,
            $query,
        );
    }

    // -- events & meters ----------------------------------------------------

    /**
     * Ingest usage events for metered billing.
     *
     * @param  Data\EventsIngest|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function ingestEvents(Data\EventsIngest|array $request): Data\EventsIngestResponse
    {
        return Data\EventsIngestResponse::from(self::client()->post('/v1/events/ingest', $request));
    }

    /**
     * List customer meters.
     *
     * @param  array<string, mixed>  $query
     * @return Page<Data\CustomerMeter>
     *
     * @throws PolarApiError
     */
    public static function listCustomerMeters(array $query = []): Page
    {
        return self::client()->page('/v1/customer-meters/', Data\CustomerMeter::class, $query);
    }

    /**
     * Get a specific customer meter by ID.
     *
     * @throws PolarApiError
     */
    public static function getCustomerMeter(string $meterId): Data\CustomerMeter
    {
        return Data\CustomerMeter::from(self::client()->get('/v1/customer-meters/' . urlencode($meterId)));
    }

    // -- analytics ----------------------------------------------------------

    /**
     * Fetch Polar metrics for a period.
     *
     * `start_date`, `end_date` and `interval` are required by the API.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws PolarApiError
     */
    public static function getMetrics(array $query): Data\MetricsResponse
    {
        return Data\MetricsResponse::from(self::client()->get('/v1/metrics/', $query));
    }

    // -- files & organizations ----------------------------------------------

    /**
     * List files.
     *
     * @param  array<string, mixed>  $query
     * @return Page<Data\FileRead>
     *
     * @throws PolarApiError
     */
    public static function listFiles(array $query = []): Page
    {
        return self::client()->page('/v1/files/', Data\FileRead::class, $query);
    }

    /**
     * List organizations the access token can reach.
     *
     * @param  array<string, mixed>  $query
     * @return Page<Data\Organization>
     *
     * @throws PolarApiError
     */
    public static function listOrganizations(array $query = []): Page
    {
        return self::client()->page('/v1/organizations/', Data\Organization::class, $query);
    }

    /**
     * Get a single organization by ID.
     *
     * @throws PolarApiError
     */
    public static function getOrganization(string $organizationId): Data\Organization
    {
        return Data\Organization::from(self::client()->get('/v1/organizations/' . urlencode($organizationId)));
    }

    // -- seats --------------------------------------------------------------

    /**
     * List the seats on a subscription or order.
     *
     * @throws PolarApiError
     */
    public static function listSeats(?string $subscriptionId = null, ?string $orderId = null): Data\SeatsList
    {
        return Data\SeatsList::from(self::client()->get('/v1/customer-seats', [
            'subscription_id' => $subscriptionId,
            'order_id' => $orderId,
        ]));
    }

    /**
     * Assign a seat to a member by email, customer id, or external id.
     *
     * @param  Data\SeatAssign|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function assignSeat(Data\SeatAssign|array $request): Data\CustomerSeat
    {
        return Data\CustomerSeat::from(self::client()->post('/v1/customer-seats', $request));
    }

    /**
     * Revoke a seat from a member.
     *
     * @throws PolarApiError
     */
    public static function revokeSeat(string $seatId): Data\CustomerSeat
    {
        return Data\CustomerSeat::from(self::client()->delete('/v1/customer-seats/' . urlencode($seatId)));
    }

    /**
     * Resend the invitation email for a pending seat.
     *
     * @throws PolarApiError
     */
    public static function resendSeatInvitation(string $seatId): Data\CustomerSeat
    {
        return Data\CustomerSeat::from(self::client()->post('/v1/customer-seats/' . urlencode($seatId) . '/resend'));
    }

    // -- license keys -------------------------------------------------------

    /**
     * List license keys (requires an organization-scoped access token).
     *
     * @param  array<string, mixed>  $query
     * @return Page<Data\LicenseKeyRead>
     *
     * @throws PolarApiError
     */
    public static function listLicenseKeys(array $query = []): Page
    {
        return self::client()->page('/v1/license-keys/', Data\LicenseKeyRead::class, $query);
    }

    /**
     * Get a license key by ID.
     *
     * @throws PolarApiError
     */
    public static function getLicenseKey(string $licenseKeyId): Data\LicenseKeyWithActivations
    {
        return Data\LicenseKeyWithActivations::from(
            self::client()->get('/v1/license-keys/' . urlencode($licenseKeyId)),
        );
    }

    /**
     * Update a license key.
     *
     * @param  Data\LicenseKeyUpdate|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function updateLicenseKey(string $licenseKeyId, Data\LicenseKeyUpdate|array $request): Data\LicenseKeyRead
    {
        return Data\LicenseKeyRead::from(
            self::client()->patch('/v1/license-keys/' . urlencode($licenseKeyId), $request),
        );
    }

    /**
     * Validate a license key.
     *
     * This uses Polar's public customer-portal route, so it needs no access token — but it does
     * need an organization id, either passed here or set as `polar.organization_id`.
     *
     * @param  array<string, mixed>|null  $conditions
     *
     * @throws PolarApiError
     */
    public static function validateLicenseKey(
        string $key,
        ?string $organizationId = null,
        ?string $activationId = null,
        ?array $conditions = null,
        ?string $benefitId = null,
        ?string $customerId = null,
        ?int $incrementUsage = null,
    ): Data\ValidatedLicenseKey {
        return Data\ValidatedLicenseKey::from(self::client()->post('/v1/customer-portal/license-keys/validate', [
            'key' => $key,
            'organization_id' => self::resolveOrganizationId($organizationId),
            'activation_id' => $activationId,
            'conditions' => $conditions,
            'benefit_id' => $benefitId,
            'customer_id' => $customerId,
            'increment_usage' => $incrementUsage,
        ]));
    }

    /**
     * Activate a license key. Public route; see {@see self::validateLicenseKey()} for auth.
     *
     * @param  array<string, mixed>|null  $conditions
     * @param  array<string, mixed>|null  $meta
     *
     * @throws PolarApiError
     */
    public static function activateLicenseKey(
        string $key,
        string $label,
        ?string $organizationId = null,
        ?array $conditions = null,
        ?array $meta = null,
    ): Data\LicenseKeyActivationRead {
        return Data\LicenseKeyActivationRead::from(self::client()->post('/v1/customer-portal/license-keys/activate', [
            'key' => $key,
            'organization_id' => self::resolveOrganizationId($organizationId),
            'label' => $label,
            'conditions' => $conditions,
            'meta' => $meta,
        ]));
    }

    /**
     * Deactivate a license key activation. Public route; see {@see self::validateLicenseKey()}.
     *
     * @throws PolarApiError
     */
    public static function deactivateLicenseKey(string $key, string $activationId, ?string $organizationId = null): void
    {
        self::client()->post('/v1/customer-portal/license-keys/deactivate', [
            'key' => $key,
            'organization_id' => self::resolveOrganizationId($organizationId),
            'activation_id' => $activationId,
        ]);
    }

    // -- custom fields ------------------------------------------------------

    /**
     * Create a custom field.
     *
     * @param  Data\CustomFieldCreate|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function createCustomField(Data\CustomFieldCreate|array $request): Data\CustomField
    {
        return Data\CustomField::from(self::client()->post('/v1/custom-fields/', $request));
    }

    /**
     * Update a custom field.
     *
     * @param  Data\CustomFieldUpdate|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function updateCustomField(string $customFieldId, Data\CustomFieldUpdate|array $request): Data\CustomField
    {
        return Data\CustomField::from(
            self::client()->patch('/v1/custom-fields/' . urlencode($customFieldId), $request),
        );
    }

    /**
     * Delete a custom field.
     *
     * @throws PolarApiError
     */
    public static function deleteCustomField(string $customFieldId): void
    {
        self::client()->delete('/v1/custom-fields/' . urlencode($customFieldId));
    }

    /**
     * List custom fields.
     *
     * @param  array<string, mixed>  $query
     * @return Page<Data\CustomField>
     *
     * @throws PolarApiError
     */
    public static function listCustomFields(array $query = []): Page
    {
        return self::client()->page('/v1/custom-fields/', Data\CustomField::class, $query);
    }

    /**
     * Get a specific custom field by ID.
     *
     * @throws PolarApiError
     */
    public static function getCustomField(string $customFieldId): Data\CustomField
    {
        return Data\CustomField::from(self::client()->get('/v1/custom-fields/' . urlencode($customFieldId)));
    }

    // -- checkout links -----------------------------------------------------

    /**
     * Create a checkout link.
     *
     * @param  Data\CheckoutLinkCreate|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function createCheckoutLink(Data\CheckoutLinkCreate|array $request): Data\CheckoutLink
    {
        return Data\CheckoutLink::from(self::client()->post('/v1/checkout-links/', $request));
    }

    /**
     * Update a checkout link.
     *
     * @param  Data\CheckoutLinkUpdate|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function updateCheckoutLink(string $checkoutLinkId, Data\CheckoutLinkUpdate|array $request): Data\CheckoutLink
    {
        return Data\CheckoutLink::from(
            self::client()->patch('/v1/checkout-links/' . urlencode($checkoutLinkId), $request),
        );
    }

    /**
     * Delete a checkout link.
     *
     * @throws PolarApiError
     */
    public static function deleteCheckoutLink(string $checkoutLinkId): void
    {
        self::client()->delete('/v1/checkout-links/' . urlencode($checkoutLinkId));
    }

    /**
     * List checkout links.
     *
     * @param  array<string, mixed>  $query
     * @return Page<Data\CheckoutLink>
     *
     * @throws PolarApiError
     */
    public static function listCheckoutLinks(array $query = []): Page
    {
        return self::client()->page('/v1/checkout-links/', Data\CheckoutLink::class, $query);
    }

    /**
     * Get a specific checkout link by ID.
     *
     * @throws PolarApiError
     */
    public static function getCheckoutLink(string $checkoutLinkId): Data\CheckoutLink
    {
        return Data\CheckoutLink::from(self::client()->get('/v1/checkout-links/' . urlencode($checkoutLinkId)));
    }

    // -- discounts ----------------------------------------------------------

    /**
     * Create a discount.
     *
     * @param  Data\DiscountCreate|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function createDiscount(Data\DiscountCreate|array $request): Data\Discount
    {
        return Data\Discount::from(self::client()->post('/v1/discounts/', $request));
    }

    /**
     * Update a discount.
     *
     * @param  Data\DiscountUpdate|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function updateDiscount(string $discountId, Data\DiscountUpdate|array $request): Data\Discount
    {
        return Data\Discount::from(self::client()->patch('/v1/discounts/' . urlencode($discountId), $request));
    }

    /**
     * Delete a discount.
     *
     * @throws PolarApiError
     */
    public static function deleteDiscount(string $discountId): void
    {
        self::client()->delete('/v1/discounts/' . urlencode($discountId));
    }

    /**
     * List discounts.
     *
     * @param  array<string, mixed>  $query
     * @return Page<Data\Discount>
     *
     * @throws PolarApiError
     */
    public static function listDiscounts(array $query = []): Page
    {
        return self::client()->page('/v1/discounts/', Data\Discount::class, $query);
    }

    /**
     * Get a specific discount by ID.
     *
     * @throws PolarApiError
     */
    public static function getDiscount(string $discountId): Data\Discount
    {
        return Data\Discount::from(self::client()->get('/v1/discounts/' . urlencode($discountId)));
    }

    // -- orders & refunds ---------------------------------------------------

    /**
     * Get an order by ID.
     *
     * @throws PolarApiError
     */
    public static function getOrder(string $orderId): Data\Order
    {
        return Data\Order::from(self::client()->get('/v1/orders/' . urlencode($orderId)));
    }

    /**
     * Create a refund for an order.
     *
     * @param  Data\RefundCreate|array<string, mixed>  $request
     *
     * @throws PolarApiError
     */
    public static function createRefund(Data\RefundCreate|array $request): Data\Refund
    {
        return Data\Refund::from(self::client()->post('/v1/refunds/', $request));
    }

    /**
     * List refunds.
     *
     * @param  array<string, mixed>  $query
     * @return Page<Data\Refund>
     *
     * @throws PolarApiError
     */
    public static function listRefunds(array $query = []): Page
    {
        return self::client()->page('/v1/refunds/', Data\Refund::class, $query);
    }

    // -- customer portal ----------------------------------------------------

    /**
     * List a customer's saved payment methods, using a customer session token.
     *
     * @return Page<Data\CustomerPaymentMethod>
     *
     * @throws PolarApiError
     */
    public static function listCustomerPaymentMethods(string $customerSessionToken, array $query = []): Page
    {
        return self::client()->page(
            '/v1/customer-portal/customers/me/payment-methods',
            Data\CustomerPaymentMethod::class,
            $query,
            token: $customerSessionToken,
        );
    }

    /**
     * Delete one of a customer's saved payment methods.
     *
     * @throws PolarApiError
     */
    public static function deleteCustomerPaymentMethod(string $customerSessionToken, string $paymentMethodId): void
    {
        self::client()->delete(
            '/v1/customer-portal/customers/me/payment-methods/' . urlencode($paymentMethodId),
            token: $customerSessionToken,
        );
    }

    /**
     * List a customer's license keys, using a customer session token.
     *
     * @param  array<string, mixed>  $query
     * @return Page<Data\LicenseKeyRead>
     *
     * @throws PolarApiError
     */
    public static function listCustomerLicenseKeys(string $customerSessionToken, array $query = []): Page
    {
        return self::client()->page(
            '/v1/customer-portal/license-keys/',
            Data\LicenseKeyRead::class,
            $query,
            token: $customerSessionToken,
        );
    }

    /**
     * Trigger generation of an order's invoice and return its URL.
     *
     * Polar generates invoices asynchronously: the POST only queues the work, so the URL is
     * read back separately. Returns null while generation is still pending.
     *
     * @throws PolarApiError
     */
    public static function getOrderInvoiceUrl(string $customerSessionToken, string $orderId): ?string
    {
        $path = '/v1/customer-portal/orders/' . urlencode($orderId) . '/invoice';

        try {
            self::client()->post($path, token: $customerSessionToken);
        } catch (PolarApiError $e) {
            // 409 means the invoice already exists, which is exactly what we want.
            if ($e->status !== 409) {
                throw $e;
            }
        }

        $invoice = self::client()->get($path, token: $customerSessionToken);

        return is_string($invoice['url'] ?? null) ? $invoice['url'] : null;
    }

    // -- configuration ------------------------------------------------------

    /**
     * Resolve an organization id from an explicit argument or the config fallback.
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
     * Reset the cached client (useful for testing).
     */
    public static function resetClient(): void
    {
        self::$client = null;
    }

    /**
     * Swap in a client instance (useful for testing).
     */
    public static function setClient(?PolarClient $client): void
    {
        self::$client = $client;
    }

    /**
     * Get or create the cached HTTP client.
     *
     * @throws Exception
     */
    public static function client(): PolarClient
    {
        if (self::$client !== null) {
            return self::$client;
        }

        $accessToken = config('polar.access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new Exception('Polar API key not set.');
        }

        $timeout = config('polar.timeout');

        return self::$client = new PolarClient(
            accessToken: $accessToken,
            baseUrl: PolarClient::resolveBaseUrl(config('polar.server')),
            version: (string) config('polar.version', PolarClient::API_VERSION),
            timeout: is_numeric($timeout) ? (int) $timeout : null,
        );
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

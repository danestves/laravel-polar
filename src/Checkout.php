<?php

namespace Danestves\LaravelPolar;

use DateTime;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Polar\Models\Components;
use Polar\Models\Errors;

class Checkout implements Responsable
{
    /** @var ?array<string, string|int|bool> */
    private ?array $metadata = null;

    /** @var ?array<string, string|int|bool|DateTime|null> */
    private ?array $customFieldData = null;

    /** @var ?array<string, string|int|bool> */
    private ?array $customerMetadata = null;

    private ?string $discountId = null;

    private bool $allowDiscountCodes = true;

    private ?int $amount = null;

    private ?string $customerId = null;

    private ?string $customerExternalId = null;

    private ?string $customerName = null;

    private ?string $customerEmail = null;

    private ?string $customerIpAddress = null;

    private ?Components\AddressInput $customerBillingAddress = null;

    private ?string $customerTaxId = null;

    private ?string $subscriptionId = null;

    private ?string $successUrl = null;

    private ?string $embedOrigin = null;

    /**
     * @param  array<string>  $products
     */
    public function __construct(private readonly array $products) {}

    /**
     * @param  array<string>  $products
     */
    public static function make(array $products): self
    {
        return new self($products);
    }

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
     * @param  ?array<string, string|int|bool>  $metadata
     */
    public function withMetadata(?array $metadata): self
    {
        $this->metadata = ($metadata === []) ? null : $metadata;

        return $this;
    }

    /**
     * Key-value object storing custom field values.
     *
     * @param  ?array<string, string|int|bool|DateTime|null>  $customFieldData
     */
    public function withCustomFieldData(?array $customFieldData): self
    {
        $this->customFieldData = ($customFieldData === []) ? null : $customFieldData;

        return $this;
    }

    /**
     * Key-value object allowing you to store additional information that'll be copied to the created customer.
     *
     * The key must be a string with a maximum length of **40 characters**. The value must be either:
     *
     * - A string with a maximum length of **500 characters**
     * - An integer
     * - A boolean
     *
     * You can store up to **50 key-value pairs**.
     *
     * @param  ?array<string, string|int|bool>  $customerMetadata
     */
    public function withCustomerMetadata(?array $customerMetadata): self
    {
        // Process input: trim strings and filter out nulls (defensive programming)
        $processed = collect($customerMetadata)
            ->map(fn($value) => is_string($value) ? trim($value) : $value)
            /** @phpstan-ignore-next-line Defensive: filter out nulls even though type doesn't allow them */
            ->filter(fn($value) => $value !== null)
            ->toArray();

        // Convert empty array to null for SDK serialization
        $this->customerMetadata = ($processed === []) ? null : $processed;

        return $this;
    }

    /**
     * ID of the discount to apply to the checkout.
     */
    public function withDiscountId(string $discountId): self
    {
        $this->discountId = $discountId;

        return $this;
    }

    /**
     * Whether to allow the customer to apply discount codes. If you apply a discount through `discount_id`, it'll still be applied, but the customer won't be able to change it.
     */
    public function withoutDiscountCodes(): self
    {
        $this->allowDiscountCodes = false;

        return $this;
    }

    /**
     * The custom amount to charge the customer.
     */
    public function withAmount(int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * ID of an existing customer in the organization. The customer data will be pre-filled in the checkout form. The resulting order will be linked to this customer.
     */
    public function withCustomerId(string $customerId): self
    {
        $this->customerId = $customerId;

        return $this;
    }

    /**
     * ID of the customer in your system. If a matching customer exists on Polar, the resulting order will be linked to this customer. Otherwise, a new customer will be created with this external ID set.
     */
    public function withCustomerExternalId(string $customerExternalId): self
    {
        $this->customerExternalId = $customerExternalId;

        return $this;
    }

    public function withCustomerName(string $customerName): self
    {
        $this->customerName = $customerName;

        return $this;
    }

    public function withCustomerEmail(string $customerEmail): self
    {
        $this->customerEmail = $customerEmail;

        return $this;
    }

    public function withCustomerIpAddress(string $customerIpAddress): self
    {
        $this->customerIpAddress = $customerIpAddress;

        return $this;
    }

    public function withCustomerBillingAddress(?Components\AddressInput $customerBillingAddress): self
    {
        $this->customerBillingAddress = $customerBillingAddress;

        return $this;
    }

    public function withCustomerTaxId(string $customerTaxId): self
    {
        $this->customerTaxId = $customerTaxId;

        return $this;
    }

    /**
     * ID of a subscription to upgrade. It must be on a free pricing. If checkout is successful, metadata set on this checkout will be copied to the subscription, and existing keys will be overwritten.
     */
    public function withSubscriptionId(string $subscriptionId): self
    {
        $this->subscriptionId = $subscriptionId;

        return $this;
    }

    /**
     * URL where the customer will be redirected after a successful payment. You can add the `checkout_id={CHECKOUT_ID}` query parameter to retrieve the checkout session id.
     */
    public function withSuccessUrl(string $successUrl): self
    {
        $this->successUrl = $successUrl;

        return $this;
    }

    /**
     * If you plan to embed the checkout session, set this to the Origin of the embedding page. It'll allow the Polar iframe to communicate with the parent page.
     */
    public function withEmbedOrigin(string $embedOrigin): self
    {
        $this->embedOrigin = $embedOrigin;

        return $this;
    }

    public function toResponse($request): RedirectResponse
    {
        return $this->redirect();
    }

    public function redirect(): RedirectResponse
    {
        return Redirect::to($this->url(), 303);
    }

    /**
     * URL where the customer can access the checkout session.
     *
     * @throws Errors\APIException
     * @throws Errors\HTTPValidationErrorThrowable
     */
    public function url(): string
    {
        $billingAddress = $this->customerBillingAddress;

        $request = new Components\CheckoutCreate(
            products: $this->products,
            metadata: $this->metadata,
            customFieldData: $this->customFieldData,
            discountId: $this->discountId,
            allowDiscountCodes: $this->allowDiscountCodes,
            amount: $this->amount,
            customerId: $this->customerId,
            externalCustomerId: $this->customerExternalId,
            customerName: $this->customerName,
            customerEmail: $this->customerEmail,
            customerIpAddress: $this->customerIpAddress,
            customerBillingAddress: $billingAddress,
            customerTaxId: $this->customerTaxId,
            customerMetadata: $this->customerMetadata,
            subscriptionId: $this->subscriptionId,
            successUrl: $this->successUrl,
            embedOrigin: $this->embedOrigin,
        );

        $checkout = LaravelPolar::createCheckoutSession($request);

        if (!$checkout->url) {
            throw new Errors\APIException('Failed to create checkout session', 500, '', null);
        }

        return $checkout->url;
    }
}

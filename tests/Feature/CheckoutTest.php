<?php

use Danestves\LaravelPolar\Checkout;
use Danestves\LaravelPolar\Data\AddressInput;
use Danestves\LaravelPolar\Enums\CountryAlpha2;
use Danestves\LaravelPolar\Enums\PresentmentCurrency;
use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Illuminate\Support\Facades\Http;

/**
 * Fake the checkout-create endpoint and return the URL it hands back.
 */
function fakeCheckoutCreate(string $url = 'https://polar.sh/checkout/123'): string
{
    fakePolar('v1/checkouts/', polarFixture('Checkout', ['url' => $url]), 201);

    return $url;
}

/**
 * The JSON body of the checkout-create request the builder just made.
 *
 * @return array<string, mixed>
 */
function sentCheckoutPayload(): array
{
    $payload = [];

    Http::assertSent(function ($request) use (&$payload) {
        $payload = $request->data();

        return true;
    });

    return $payload;
}

it('sends the products it was built with', function () {
    fakeCheckoutCreate();

    Checkout::make(['product_123'])->url();

    expect(sentCheckoutPayload()['products'])->toBe(['product_123']);
});

it('returns the URL Polar responds with', function () {
    $url = fakeCheckoutCreate();

    expect(Checkout::make(['product_123'])->url())->toBe($url);
});

it('redirects to the checkout URL with a 303', function () {
    $url = fakeCheckoutCreate();

    $redirect = Checkout::make(['product_123'])->redirect();

    expect($redirect->getTargetUrl())->toBe($url)
        ->and($redirect->getStatusCode())->toBe(303);
});

it('is responsable, redirecting to the checkout URL', function () {
    $url = fakeCheckoutCreate();

    $response = Checkout::make(['product_123'])->toResponse(request());

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe($url);
});

it('surfaces a Polar error when the checkout cannot be created', function () {
    fakePolar('v1/checkouts/', ['detail' => 'Product not found'], 404);

    expect(fn() => Checkout::make(['product_123'])->url())
        ->toThrow(PolarApiError::class);

    expect(fn() => Checkout::make(['product_123'])->redirect())
        ->toThrow(PolarApiError::class);
});

it('raises a clear error when Polar returns a checkout without a URL', function () {
    fakePolar('v1/checkouts/', polarFixture('Checkout', ['url' => '']), 201);

    expect(fn() => Checkout::make(['product_123'])->url())
        ->toThrow(PolarApiError::class, 'without a URL');
});

it('sends every configured option using Polar field names', function () {
    fakeCheckoutCreate();

    Checkout::make(['product_123'])
        ->withCustomerName('John Doe')
        ->withCustomerEmail('john@doe.com')
        ->withCustomerId('cus_123')
        ->withCustomerExternalId('ext_123')
        ->withCustomerIpAddress('203.0.113.4')
        ->withCustomerTaxId('TAX123')
        ->withCustomerBillingAddress(new AddressInput(country: CountryAlpha2::US, city: 'Austin'))
        ->withDiscountId('discount_123')
        ->withoutDiscountCodes()
        ->withAmount(5000)
        ->withCurrency(PresentmentCurrency::Usd)
        ->withSubscriptionId('sub_123')
        ->withMetadata(['key' => 'value'])
        ->withCustomFieldData(['field1' => 'data1'])
        ->withCustomerMetadata(['plan' => 'pro'])
        ->withSuccessUrl('https://example.com/success')
        ->withReturnUrl('https://example.com/back')
        ->withEmbedOrigin('https://example.com')
        ->url();

    expect(sentCheckoutPayload())->toMatchArray([
        'products' => ['product_123'],
        'customer_name' => 'John Doe',
        'customer_email' => 'john@doe.com',
        'customer_id' => 'cus_123',
        'external_customer_id' => 'ext_123',
        'customer_ip_address' => '203.0.113.4',
        'customer_tax_id' => 'TAX123',
        'discount_id' => 'discount_123',
        'allow_discount_codes' => false,
        'amount' => 5000,
        'currency' => 'usd',
        'subscription_id' => 'sub_123',
        'metadata' => ['key' => 'value'],
        'custom_field_data' => ['field1' => 'data1'],
        'customer_metadata' => ['plan' => 'pro'],
        'success_url' => 'https://example.com/success',
        'return_url' => 'https://example.com/back',
        'embed_origin' => 'https://example.com',
    ]);
});

it('serialises a billing address into Polar field names', function () {
    fakeCheckoutCreate();

    Checkout::make(['product_123'])
        ->withCustomerBillingAddress(new AddressInput(
            country: CountryAlpha2::GB,
            line1: '1 High Street',
            postalCode: 'SW1A 1AA',
            city: 'London',
        ))
        ->url();

    expect(sentCheckoutPayload()['customer_billing_address'])->toMatchArray([
        'country' => 'GB',
        'line1' => '1 High Street',
        'postal_code' => 'SW1A 1AA',
        'city' => 'London',
    ]);
});

it('omits empty metadata rather than sending an empty object', function (string $method, string $field) {
    fakeCheckoutCreate();

    Checkout::make(['product_123'])->{$method}([])->url();

    expect(sentCheckoutPayload())->not->toHaveKey($field);
})->with([
    ['withMetadata', 'metadata'],
    ['withCustomFieldData', 'custom_field_data'],
    ['withCustomerMetadata', 'customer_metadata'],
]);

it('omits metadata when explicitly given null', function () {
    fakeCheckoutCreate();

    Checkout::make(['product_123'])->withMetadata(null)->url();

    expect(sentCheckoutPayload())->not->toHaveKey('metadata');
});

it('trims whitespace from customer metadata values', function () {
    fakeCheckoutCreate();

    Checkout::make(['product_123'])
        ->withCustomerMetadata(['name' => '  John Doe  '])
        ->url();

    expect(sentCheckoutPayload()['customer_metadata'])->toBe(['name' => 'John Doe']);
});

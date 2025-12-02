<?php

use Danestves\LaravelPolar\Checkout;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Support\Facades\Config;
use Polar\Models\Errors;
use Polar\Models\Operations;
use Psr\Http\Message\ResponseInterface;

beforeEach(function () {
    Config::set('polar.access_token', 'test-token');
    Config::set('polar.server', 'sandbox');
});

afterEach(function () {
    LaravelPolar::resetSdk();

    Mockery::close();
});

function createMockedSdkWithCheckouts(): array
{
    $sdkConfig = Mockery::mock(\Polar\SDKConfiguration::class);
    $sdkConfig->shouldReceive('getTemplatedServerUrl')->andReturn('https://sandbox-api.polar.sh');
    $hooks = Mockery::mock(\Polar\Hooks\SDKHooks::class);
    $mockClient = Mockery::mock(\GuzzleHttp\ClientInterface::class);
    $sdkRequestContext = new \Polar\Hooks\SDKRequestContext('https://sandbox-api.polar.sh', $mockClient);
    $hooks->shouldReceive('sdkInit')->andReturn($sdkRequestContext);
    $reflectionConfig = new \ReflectionClass($sdkConfig);
    $hooksProperty = $reflectionConfig->getProperty('hooks');
    $hooksProperty->setAccessible(true);
    $hooksProperty->setValue($sdkConfig, $hooks);
    $clientProperty = $reflectionConfig->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($sdkConfig, $mockClient);
    $sdk = new \Polar\Polar($sdkConfig);
    $checkouts = Mockery::mock(\Polar\Checkouts::class);
    $reflectionSdk = new \ReflectionClass($sdk);
    $checkoutsProperty = $reflectionSdk->getProperty('checkouts');
    $checkoutsProperty->setAccessible(true);
    $checkoutsProperty->setValue($sdk, $checkouts);

    return ['sdk' => $sdk, 'checkouts' => $checkouts];
}

it('can initiate a new checkout', function () {
    $checkout = Checkout::make(['product_123']);

    expect($checkout)->toBeInstanceOf(Checkout::class);

    $reflection = new \ReflectionClass($checkout);
    $productsProperty = $reflection->getProperty('products');
    $productsProperty->setAccessible(true);

    expect($productsProperty->getValue($checkout))->toBe(['product_123']);
});

it('can be redirected successfully', function () {
    $mocked = createMockedSdkWithCheckouts();
    $sdk = $mocked['sdk'];
    $checkouts = $mocked['checkouts'];

    $mockCheckout = Mockery::mock(\Polar\Models\Components\Checkout::class);
    $mockCheckout->url = 'https://polar.sh/checkout/123';

    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\CheckoutsCreateResponse(
        contentType: 'application/json',
        statusCode: 201,
        rawResponse: $mockRawResponse,
        checkout: $mockCheckout,
    );
    $checkouts->shouldReceive('create')->andReturn($response);

    setLaravelPolarSdk($sdk);

    $checkout = Checkout::make(['product_123']);
    $redirect = $checkout->redirect();

    expect($redirect->getTargetUrl())->toBe('https://polar.sh/checkout/123');
});

it('throws exception when redirect fails', function () {
    $mocked = createMockedSdkWithCheckouts();
    $sdk = $mocked['sdk'];
    $checkouts = $mocked['checkouts'];
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\CheckoutsCreateResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: $mockRawResponse,
        checkout: null,
    );
    $checkouts->shouldReceive('create')->andReturn($response);

    setLaravelPolarSdk($sdk);

    $checkout = Checkout::make(['product_123']);

    expect(fn() => $checkout->redirect())
        ->toThrow(Errors\APIException::class);
});

it('can set prefilled fields with dedicated methods', function () {
    $checkout = Checkout::make(['product_123'])
        ->withCustomerName('John Doe')
        ->withCustomerEmail('john@doe.com');

    $reflection = new \ReflectionClass($checkout);

    $nameProperty = $reflection->getProperty('customerName');
    $nameProperty->setAccessible(true);
    expect($nameProperty->getValue($checkout))->toBe('John Doe');

    $emailProperty = $reflection->getProperty('customerEmail');
    $emailProperty->setAccessible(true);
    expect($emailProperty->getValue($checkout))->toBe('john@doe.com');
});

it('can include metadata', function () {
    $checkout = Checkout::make(['product_123'])
        ->withMetadata(['batch_id' => '789']);

    $reflection = new \ReflectionClass($checkout);
    $metadataProperty = $reflection->getProperty('metadata');
    $metadataProperty->setAccessible(true);

    expect($metadataProperty->getValue($checkout))->toBe(['batch_id' => '789']);
});

it('can include prefilled fields and metadata', function () {
    $checkout = Checkout::make(['product_123'])
        ->withCustomerName('John Doe')
        ->withMetadata(['batch_id' => '789']);

    $reflection = new \ReflectionClass($checkout);

    $nameProperty = $reflection->getProperty('customerName');
    $nameProperty->setAccessible(true);
    expect($nameProperty->getValue($checkout))->toBe('John Doe');

    $metadataProperty = $reflection->getProperty('metadata');
    $metadataProperty->setAccessible(true);
    expect($metadataProperty->getValue($checkout))->toBe(['batch_id' => '789']);
});

it('can generate checkout URL', function () {
    $mocked = createMockedSdkWithCheckouts();
    $sdk = $mocked['sdk'];
    $checkouts = $mocked['checkouts'];

    $mockCheckout = Mockery::mock(\Polar\Models\Components\Checkout::class);
    $mockCheckout->url = 'https://polar.sh/checkout/123';

    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\CheckoutsCreateResponse(
        contentType: 'application/json',
        statusCode: 201,
        rawResponse: $mockRawResponse,
        checkout: $mockCheckout,
    );
    $checkouts->shouldReceive('create')->andReturn($response);

    setLaravelPolarSdk($sdk);

    $checkout = Checkout::make(['product_123']);

    expect($checkout->url())->toBe('https://polar.sh/checkout/123');
});

it('throws exception when URL generation fails', function () {
    $mocked = createMockedSdkWithCheckouts();
    $sdk = $mocked['sdk'];
    $checkouts = $mocked['checkouts'];
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\CheckoutsCreateResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: $mockRawResponse,
        checkout: null,
    );
    $checkouts->shouldReceive('create')->andReturn($response);

    setLaravelPolarSdk($sdk);

    $checkout = Checkout::make(['product_123']);

    expect(fn() => $checkout->url())
        ->toThrow(Errors\APIException::class);
});

it('implements Responsable contract successfully', function () {
    $mocked = createMockedSdkWithCheckouts();
    $sdk = $mocked['sdk'];
    $checkouts = $mocked['checkouts'];

    $mockCheckout = Mockery::mock(\Polar\Models\Components\Checkout::class);
    $mockCheckout->url = 'https://polar.sh/checkout/123';

    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\CheckoutsCreateResponse(
        contentType: 'application/json',
        statusCode: 201,
        rawResponse: $mockRawResponse,
        checkout: $mockCheckout,
    );
    $checkouts->shouldReceive('create')->andReturn($response);

    setLaravelPolarSdk($sdk);

    $checkout = Checkout::make(['product_123']);
    $response = $checkout->toResponse(request());

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);
    expect($response->getTargetUrl())->toBe('https://polar.sh/checkout/123');
});

it('throws exception when Responsable contract fails', function () {
    $mocked = createMockedSdkWithCheckouts();
    $sdk = $mocked['sdk'];
    $checkouts = $mocked['checkouts'];
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\CheckoutsCreateResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: $mockRawResponse,
        checkout: null,
    );
    $checkouts->shouldReceive('create')->andReturn($response);

    setLaravelPolarSdk($sdk);

    $checkout = Checkout::make(['product_123']);

    expect(fn() => $checkout->toResponse(request()))
        ->toThrow(Errors\APIException::class);
});

it('can set all checkout options', function () {
    $checkout = Checkout::make(['product_123'])
        ->withCustomerName('John Doe')
        ->withCustomerEmail('john@doe.com')
        ->withCustomerTaxId('TAX123')
        ->withDiscountId('discount_123')
        ->withAmount(5000)
        ->withMetadata(['key' => 'value'])
        ->withCustomFieldData(['field1' => 'data1'])
        ->withSuccessUrl('https://example.com/success')
        ->withEmbedOrigin('https://example.com')
        ->withoutDiscountCodes();

    $reflection = new \ReflectionClass($checkout);

    $customerNameProperty = $reflection->getProperty('customerName');
    $customerNameProperty->setAccessible(true);
    expect($customerNameProperty->getValue($checkout))->toBe('John Doe');

    $customerEmailProperty = $reflection->getProperty('customerEmail');
    $customerEmailProperty->setAccessible(true);
    expect($customerEmailProperty->getValue($checkout))->toBe('john@doe.com');

    $customerTaxIdProperty = $reflection->getProperty('customerTaxId');
    $customerTaxIdProperty->setAccessible(true);
    expect($customerTaxIdProperty->getValue($checkout))->toBe('TAX123');

    $discountIdProperty = $reflection->getProperty('discountId');
    $discountIdProperty->setAccessible(true);
    expect($discountIdProperty->getValue($checkout))->toBe('discount_123');

    $amountProperty = $reflection->getProperty('amount');
    $amountProperty->setAccessible(true);
    expect($amountProperty->getValue($checkout))->toBe(5000);

    $metadataProperty = $reflection->getProperty('metadata');
    $metadataProperty->setAccessible(true);
    expect($metadataProperty->getValue($checkout))->toBe(['key' => 'value']);

    $customFieldDataProperty = $reflection->getProperty('customFieldData');
    $customFieldDataProperty->setAccessible(true);
    expect($customFieldDataProperty->getValue($checkout))->toBe(['field1' => 'data1']);

    $successUrlProperty = $reflection->getProperty('successUrl');
    $successUrlProperty->setAccessible(true);
    expect($successUrlProperty->getValue($checkout))->toBe('https://example.com/success');

    $embedOriginProperty = $reflection->getProperty('embedOrigin');
    $embedOriginProperty->setAccessible(true);
    expect($embedOriginProperty->getValue($checkout))->toBe('https://example.com');

    $allowDiscountCodesProperty = $reflection->getProperty('allowDiscountCodes');
    $allowDiscountCodesProperty->setAccessible(true);
    expect($allowDiscountCodesProperty->getValue($checkout))->toBeFalse();
});

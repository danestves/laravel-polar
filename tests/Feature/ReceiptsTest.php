<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Config;
use Mockery;
use Polar\Models\Components;
use Polar\Models\Operations;

beforeEach(function () {
    Config::set('polar.access_token', 'test-token');
    Config::set('polar.server', 'sandbox');
});

afterEach(function () {
    resetLaravelPolarSdk();
    Mockery::close();
});

function createMockedSdkWithCustomerPortalOrders(): array
{
    $base = createBaseMockedSdk();
    $sdk = $base['sdk'];

    $customerPortal = Mockery::mock(\Polar\CustomerPortal::class);
    $customerPortalOrders = Mockery::mock(\Polar\PolarOrders::class);
    $customerPortal->orders = $customerPortalOrders;

    $reflectionSdk = new \ReflectionClass($sdk);
    $cpProperty = $reflectionSdk->getProperty('customerPortal');
    $cpProperty->setAccessible(true);
    $cpProperty->setValue($sdk, $customerPortal);

    $customerSessions = Mockery::mock(\Polar\CustomerSessions::class);
    $csProperty = $reflectionSdk->getProperty('customerSessions');
    $csProperty->setAccessible(true);
    $csProperty->setValue($sdk, $customerSessions);

    return [
        'sdk' => $sdk,
        'customerPortalOrders' => $customerPortalOrders,
        'customerSessions' => $customerSessions,
    ];
}

function stubCustomerSession(\Mockery\MockInterface $customerSessions, string $token = 'cs_token'): void
{
    $session = Mockery::mock(Components\CustomerSession::class);
    $session->token = $token;
    $session->customerPortalUrl = 'https://polar.sh/portal';

    $response = new Operations\CustomerSessionsCreateResponse(
        contentType: 'application/json',
        statusCode: 201,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        customerSession: $session,
    );

    $customerSessions->shouldReceive('create')->andReturn($response);
}

it('$order->receiptUrl returns the URL from the generateInvoice response', function () {
    $mocked = createMockedSdkWithCustomerPortalOrders();
    setLaravelPolarSdk($mocked['sdk']);

    stubCustomerSession($mocked['customerSessions']);

    $order = Order::factory()->paid()->create([
        'polar_id' => 'ord_with_invoice',
        'customer_id' => 'cust_xyz',
    ]);

    $invoiceResponse = new Operations\CustomerPortalOrdersGenerateInvoiceResponse(
        contentType: 'application/json',
        statusCode: 202,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        any: ['url' => 'https://polar.sh/i/abc.pdf'],
    );

    $mocked['customerPortalOrders']->shouldReceive('generateInvoice')
        ->once() // memoization: second call should hit the cache
        ->withArgs(function ($security, string $id) {
            return $id === 'ord_with_invoice'
                && $security instanceof Operations\CustomerPortalOrdersGenerateInvoiceSecurity
                && $security->customerSession === 'cs_token';
        })
        ->andReturn($invoiceResponse);

    expect($order->receiptUrl())->toBe('https://polar.sh/i/abc.pdf');
    expect($order->receiptUrl())->toBe('https://polar.sh/i/abc.pdf'); // memoized
});

it('$order->receiptUrl returns null when polar_id is missing', function () {
    $order = Order::factory()->paid()->create(['polar_id' => null]);

    expect($order->receiptUrl())->toBeNull();
});

it('$order->receiptUrl returns null when the response body has no url field', function () {
    $mocked = createMockedSdkWithCustomerPortalOrders();
    setLaravelPolarSdk($mocked['sdk']);

    stubCustomerSession($mocked['customerSessions']);

    $order = Order::factory()->paid()->create([
        'polar_id' => 'ord_no_url',
        'customer_id' => 'cust_xyz',
    ]);

    $invoiceResponse = new Operations\CustomerPortalOrdersGenerateInvoiceResponse(
        contentType: 'application/json',
        statusCode: 202,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        any: ['status' => 'pending'],
    );

    $mocked['customerPortalOrders']->shouldReceive('generateInvoice')->andReturn($invoiceResponse);

    expect($order->receiptUrl())->toBeNull();
});

it('$order->downloadInvoice returns a redirect to the URL', function () {
    $mocked = createMockedSdkWithCustomerPortalOrders();
    setLaravelPolarSdk($mocked['sdk']);

    stubCustomerSession($mocked['customerSessions']);

    $order = Order::factory()->paid()->create([
        'polar_id' => 'ord_dl',
        'customer_id' => 'cust_xyz',
    ]);

    $invoiceResponse = new Operations\CustomerPortalOrdersGenerateInvoiceResponse(
        contentType: 'application/json',
        statusCode: 202,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        any: ['url' => 'https://polar.sh/i/xyz.pdf'],
    );

    $mocked['customerPortalOrders']->shouldReceive('generateInvoice')->andReturn($invoiceResponse);

    $redirect = $order->downloadInvoice();

    expect($redirect)->toBeInstanceOf(RedirectResponse::class);
    expect($redirect->getTargetUrl())->toBe('https://polar.sh/i/xyz.pdf');
});

it('$order->downloadInvoice throws when no URL is available', function () {
    $order = Order::factory()->paid()->create(['polar_id' => null]);

    expect(fn() => $order->downloadInvoice())
        ->toThrow(\RuntimeException::class, 'No receipt URL available');
});

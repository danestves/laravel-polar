<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Support\Facades\Config;
use Mockery;
use Polar\Models\Components;
use Polar\Models\Errors;
use Polar\Models\Operations;

beforeEach(function () {
    Config::set('polar.access_token', 'test-token');
    Config::set('polar.server', 'sandbox');
});

afterEach(function () {
    resetLaravelPolarSdk();
    Mockery::close();
});

function createMockedSdkWithCustomerPortalCustomers(): array
{
    $base = createBaseMockedSdk();
    $sdk = $base['sdk'];

    $customerPortal = Mockery::mock(\Polar\CustomerPortal::class);
    $customerPortalCustomers = Mockery::mock(\Polar\PolarCustomers::class);
    $customerPortal->customers = $customerPortalCustomers;

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
        'customerPortalCustomers' => $customerPortalCustomers,
        'customerSessions' => $customerSessions,
    ];
}

function stubCustomerSessionForPm(\Mockery\MockInterface $customerSessions, string $token = 'cs_token'): void
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

function makeBillableWithCustomer(string $polarId = 'cust_xyz'): User
{
    $user = User::factory()->create();
    $user->customer()->save(new Customer(['polar_id' => $polarId]));

    return $user->fresh();
}

it('$user->paymentMethods() returns a Collection of payment methods', function () {
    $mocked = createMockedSdkWithCustomerPortalCustomers();
    setLaravelPolarSdk($mocked['sdk']);

    stubCustomerSessionForPm($mocked['customerSessions']);

    $user = makeBillableWithCustomer();

    $card1 = Mockery::mock(Components\PaymentMethodCard::class);
    $card2 = Mockery::mock(Components\PaymentMethodGeneric::class);

    $list = new Components\ListResourceCustomerPaymentMethod(
        items: [$card1, $card2],
        pagination: new Components\Pagination(totalCount: 2, maxPage: 1),
    );

    $response = new Operations\CustomerPortalCustomersListPaymentMethodsResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        listResourceCustomerPaymentMethod: $list,
    );

    $mocked['customerPortalCustomers']->shouldReceive('listPaymentMethods')
        ->once()
        ->withArgs(function ($security) {
            return $security instanceof Operations\CustomerPortalCustomersListPaymentMethodsSecurity
                && $security->customerSession === 'cs_token';
        })
        ->andReturn((function () use ($response) {
            yield $response;
        })());

    $methods = $user->paymentMethods();

    expect($methods)->toHaveCount(2);
    expect($methods->first())->toBe($card1);
    expect($methods->last())->toBe($card2);
});

it('$user->paymentMethods() returns an empty Collection when the SDK returns no items', function () {
    $mocked = createMockedSdkWithCustomerPortalCustomers();
    setLaravelPolarSdk($mocked['sdk']);

    stubCustomerSessionForPm($mocked['customerSessions']);

    $user = makeBillableWithCustomer();

    $list = new Components\ListResourceCustomerPaymentMethod(
        items: [],
        pagination: new Components\Pagination(totalCount: 0, maxPage: 1),
    );

    $response = new Operations\CustomerPortalCustomersListPaymentMethodsResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        listResourceCustomerPaymentMethod: $list,
    );

    $mocked['customerPortalCustomers']->shouldReceive('listPaymentMethods')
        ->once()
        ->andReturn((function () use ($response) {
            yield $response;
        })());

    expect($user->paymentMethods())->toHaveCount(0);
});

it('$user->paymentMethods() throws InvalidCustomer when the billable has no Polar customer', function () {
    $user = User::factory()->create();

    expect(fn() => $user->paymentMethods())->toThrow(InvalidCustomer::class);
});

it('$user->deletePaymentMethod() succeeds on 200 and 204 responses', function () {
    $mocked = createMockedSdkWithCustomerPortalCustomers();
    setLaravelPolarSdk($mocked['sdk']);

    stubCustomerSessionForPm($mocked['customerSessions']);

    $user = makeBillableWithCustomer();

    $ok200 = new Operations\CustomerPortalCustomersDeletePaymentMethodResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
    );

    $mocked['customerPortalCustomers']->shouldReceive('deletePaymentMethod')
        ->once()
        ->withArgs(function ($security, string $id) {
            return $security instanceof Operations\CustomerPortalCustomersDeletePaymentMethodSecurity
                && $security->customerSession === 'cs_token'
                && $id === 'pm_200';
        })
        ->andReturn($ok200);

    $user->deletePaymentMethod('pm_200');

    $ok204 = new Operations\CustomerPortalCustomersDeletePaymentMethodResponse(
        contentType: 'application/json',
        statusCode: 204,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
    );

    $mocked['customerPortalCustomers']->shouldReceive('deletePaymentMethod')
        ->once()
        ->andReturn($ok204);

    $user->deletePaymentMethod('pm_204');
});

it('$user->deletePaymentMethod() throws APIException on non-200/204', function () {
    $mocked = createMockedSdkWithCustomerPortalCustomers();
    setLaravelPolarSdk($mocked['sdk']);

    stubCustomerSessionForPm($mocked['customerSessions']);

    $user = makeBillableWithCustomer();

    $err = new Operations\CustomerPortalCustomersDeletePaymentMethodResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
    );

    $mocked['customerPortalCustomers']->shouldReceive('deletePaymentMethod')->andReturn($err);

    expect(fn() => $user->deletePaymentMethod('pm_bad'))
        ->toThrow(Errors\APIException::class);
});

it('$user->deletePaymentMethod() throws InvalidCustomer when the billable has no Polar customer', function () {
    $user = User::factory()->create();

    expect(fn() => $user->deletePaymentMethod('pm_xyz'))->toThrow(InvalidCustomer::class);
});

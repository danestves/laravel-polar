<?php

declare(strict_types=1);

namespace Tests\Feature;

use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Tests\Fixtures\User;
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

function stubCustomerSessionForPortal(string $portalUrl = 'https://polar.sh/portal/cs_token'): void
{
    $base = createBaseMockedSdk();
    setLaravelPolarSdk($base['sdk']);

    $customerSessions = Mockery::mock(\Polar\CustomerSessions::class);
    $reflectionSdk = new \ReflectionClass($base['sdk']);
    $property = $reflectionSdk->getProperty('customerSessions');
    $property->setAccessible(true);
    $property->setValue($base['sdk'], $customerSessions);

    $session = Mockery::mock(Components\CustomerSession::class);
    $session->token = 'cs_token';
    $session->customerPortalUrl = $portalUrl;

    $response = new Operations\CustomerSessionsCreateResponse(
        contentType: 'application/json',
        statusCode: 201,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        customerSession: $session,
    );

    $customerSessions->shouldReceive('create')->andReturn($response);
}

it('redirectToCustomerPortal returns Inertia::location when called from an Inertia request', function () {
    stubCustomerSessionForPortal('https://polar.sh/portal/cs_token');

    $user = User::factory()->create();
    $user->customer()->save(new Customer(['polar_id' => 'cust_xyz']));
    $user = $user->fresh();

    $request = \Illuminate\Http\Request::create('http://localhost/billing/portal', 'GET');
    $request->headers->set('X-Inertia', 'true');
    app()->instance('request', $request);

    $response = $user->redirectToCustomerPortal();

    expect($response->getStatusCode())->toBe(409);
    expect($response->headers->get('X-Inertia-Location'))->toBe('https://polar.sh/portal/cs_token');
});

it('redirectToCustomerPortal returns a plain redirect when called from a non-Inertia request', function () {
    stubCustomerSessionForPortal('https://polar.sh/portal/cs_token');

    $user = User::factory()->create();
    $user->customer()->save(new Customer(['polar_id' => 'cust_xyz']));
    $user = $user->fresh();

    $request = \Illuminate\Http\Request::create('http://localhost/billing/portal', 'GET');
    app()->instance('request', $request);

    $response = $user->redirectToCustomerPortal();

    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);
    expect($response->getTargetUrl())->toBe('https://polar.sh/portal/cs_token');
});

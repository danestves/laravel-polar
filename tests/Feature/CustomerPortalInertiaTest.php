<?php

declare(strict_types=1);

namespace Tests\Feature;

use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Tests\Fixtures\User;

/**
 * Fake the customer-session endpoint that backs the portal redirect.
 */
function stubCustomerSessionForPortal(string $portalUrl = 'https://polar.sh/portal/cs_token'): void
{
    fakePolar('v1/customer-sessions/', polarFixture('CustomerSession', [
        'token' => 'cs_token',
        'customer_portal_url' => $portalUrl,
    ]), 201);
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

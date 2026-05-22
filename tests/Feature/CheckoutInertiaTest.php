<?php

declare(strict_types=1);

namespace Tests\Feature;

use Danestves\LaravelPolar\Checkout;
use Illuminate\Support\Facades\Config;
use Mockery;

beforeEach(function () {
    Config::set('polar.access_token', 'test-token');
    Config::set('polar.server', 'sandbox');
});

afterEach(function () {
    resetLaravelPolarSdk();
    Mockery::close();
});

it('Checkout::toResponse returns Inertia::location response when called from an Inertia request', function () {
    $checkout = Mockery::mock(Checkout::class)->makePartial();
    $checkout->shouldReceive('url')->andReturn('https://sandbox.polar.sh/checkout/polar_c_test');

    // Inertia::location() checks the global request via Request::inertia(), so
    // set the header on the container request as well as the passed request.
    $request = \Illuminate\Http\Request::create('http://localhost/billing/checkout', 'POST');
    $request->headers->set('X-Inertia', 'true');
    app()->instance('request', $request);

    $response = $checkout->toResponse($request);

    expect($response->getStatusCode())->toBe(409);
    expect($response->headers->get('X-Inertia-Location'))->toBe('https://sandbox.polar.sh/checkout/polar_c_test');
});

it('Checkout::toResponse returns a 303 redirect when called from a non-Inertia request', function () {
    $checkout = Mockery::mock(Checkout::class)->makePartial();
    $checkout->shouldReceive('url')->andReturn('https://sandbox.polar.sh/checkout/polar_c_test');

    $request = \Illuminate\Http\Request::create('http://localhost/billing/checkout', 'POST');
    app()->instance('request', $request);

    $response = $checkout->toResponse($request);

    expect($response->getStatusCode())->toBe(303);
    expect($response->headers->get('Location'))->toBe('https://sandbox.polar.sh/checkout/polar_c_test');
});

it('Checkout::redirect always returns a plain 303 regardless of the Inertia header', function () {
    $checkout = Mockery::mock(Checkout::class)->makePartial();
    $checkout->shouldReceive('url')->andReturn('https://sandbox.polar.sh/checkout/polar_c_test');

    $response = $checkout->redirect();

    expect($response->getStatusCode())->toBe(303);
    expect($response->headers->get('Location'))->toBe('https://sandbox.polar.sh/checkout/polar_c_test');
});

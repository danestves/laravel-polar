<?php

use Danestves\LaravelPolar\Testing\PolarFake;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;

it('registers a bare response body and matches on the path', function () {
    $polar = new PolarFake([
        'GET /v1/checkouts/' => ['id' => 'checkout_123', 'status' => 'open'],
    ]);

    $response = Http::get(polarUrl('v1/checkouts/') . '?foo=bar');

    expect($response->status())->toBe(200)
        ->and($response->json('id'))->toBe('checkout_123')
        ->and($response->json('status'))->toBe('open');

    $polar->assertSent('GET', '/v1/checkouts/');
    $polar->assertSent('GET', '/v1/checkouts/', fn($request, $response) => $response->json('id') === 'checkout_123');
    $polar->assertSentCount(1);
    $polar->assertNotSent('POST', '/v1/checkouts/');
    $polar->assertNotSent('GET', '/v1/orders/');
});

it('registers a response envelope with an explicit status and body', function () {
    $polar = new PolarFake([
        'POST /v1/refunds/' => ['status' => 201, 'body' => ['id' => 'refund_123']],
    ]);

    $response = Http::post(polarUrl('v1/refunds/'), ['order_id' => 'order_123']);

    expect($response->status())->toBe(201)
        ->and($response->json('id'))->toBe('refund_123');

    $polar->assertSent('POST', '/v1/refunds/');
});

it('uses respond() to add responses after construction', function () {
    $polar = new PolarFake();

    $polar->respond('GET', '/v1/orders/', ['items' => [], 'pagination' => ['total_count' => 0, 'max_page' => 1]]);

    $response = Http::get(polarUrl('v1/orders/'));

    expect($response->json('pagination.max_page'))->toBe(1);
    $polar->assertSent('GET', '/v1/orders/');
});

it('supports wildcards when matching and asserting', function () {
    $polar = new PolarFake();

    $polar->respond('GET', '/v1/orders/*', [
        'items' => [],
        'pagination' => ['total_count' => 0, 'max_page' => 1],
    ]);

    $response = Http::get(polarUrl('v1/orders/order_123') . '?limit=10&page=1');

    expect($response->status())->toBe(200);

    $polar->assertSent('GET', '/v1/orders/*');
    $polar->assertSent('GET', '/v1/orders/order_123');
    $polar->assertNotSent('POST', '/v1/orders/*');
});

it('returns a descriptive 404 for unmatched requests', function () {
    $polar = new PolarFake();

    $response = Http::get(polarUrl('v1/unknown/'));

    expect($response->status())->toBe(404)
        ->and($response->json('detail'))->toBe('No Polar fake response registered for GET /v1/unknown/');

    expect($polar->unmatched())->toHaveCount(1);
    $polar->assertNotSent('GET', '/v1/orders/');
});

it('fails assertions with helpful messages', function () {
    $polar = new PolarFake();

    Http::get(polarUrl('v1/anything/'));

    expect(fn() => $polar->assertSent('GET', '/v1/orders/'))
        ->toThrow(AssertionFailedError::class, 'Expected a GET request matching [/v1/orders/]');

    expect(fn() => $polar->assertNotSent('GET', '/v1/anything/'))
        ->toThrow(AssertionFailedError::class);

    expect(fn() => $polar->assertSentCount(2))
        ->toThrow(AssertionFailedError::class, 'Expected 2 request(s) to have been sent, but 1 were.');

    expect(fn() => $polar->assertNothingSent())
        ->toThrow(AssertionFailedError::class);
});

it('resets registered responses and recordings', function () {
    $polar = new PolarFake([
        'GET /v1/checkouts/' => ['id' => 'checkout_123'],
    ]);

    Http::get(polarUrl('v1/checkouts/'));
    $polar->assertSentCount(1);

    $polar->reset();

    $polar->assertNothingSent();

    $response = Http::get(polarUrl('v1/checkouts/'));

    expect($response->status())->toBe(404);
});

it('passes response headers through to the caller', function () {
    $polar = new PolarFake([
        'GET /v1/limited/' => [
            'status' => 429,
            'body' => ['detail' => 'slow down'],
            'headers' => ['Retry-After' => '7'],
        ],
    ]);

    $response = Http::get(polarUrl('v1/limited/'));

    expect($response->status())->toBe(429)
        ->and($response->header('Retry-After'))->toBe('7');

    $polar->assertSent('GET', '/v1/limited/');
});

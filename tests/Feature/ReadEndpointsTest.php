<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Support\Facades\Http;

/**
 * A spec-valid meter payload.
 *
 * PolarFixtures has no standalone `Meter` skeleton, so this mirrors the meter nested in
 * the `CustomerMeter` fixture with an explicit count aggregation.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function meterFixture(array $overrides = []): array
{
    return array_replace([
        'metadata' => [],
        'created_at' => '2026-01-01T00:00:00Z',
        'modified_at' => null,
        'id' => 'meter_1',
        'name' => 'API calls',
        'unit' => 'scalar',
        'filter' => [
            'conjunction' => 'and',
            'clauses' => [],
        ],
        'aggregation' => ['func' => 'count'],
        'organization_id' => '00000000-0000-0000-0000-000000000000',
        'custom_label' => null,
        'custom_multiplier' => null,
        'archived_at' => null,
    ], $overrides);
}

it('gets a subscription', function () {
    fakePolar('v1/subscriptions/sub_1', polarFixture('Subscription', ['id' => 'sub_1', 'status' => 'active']));

    $subscription = LaravelPolar::getSubscription('sub_1');

    expect($subscription)->toBeInstanceOf(Data\Subscription::class)
        ->and($subscription->id)->toBe('sub_1')
        ->and($subscription->status->value)->toBe('active');

    Http::assertSent(fn($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/v1/subscriptions/sub_1'));
});

it('lists subscriptions', function () {
    fakePolarList('v1/subscriptions/*', [
        polarFixture('Subscription', ['id' => 'sub_1']),
        polarFixture('Subscription', ['id' => 'sub_2']),
    ]);

    $page = LaravelPolar::listSubscriptions(['customer_id' => 'cus_1']);

    expect($page)->toHaveCount(2)
        ->and($page->first())->toBeInstanceOf(Data\Subscription::class)
        ->and($page->first()->id)->toBe('sub_1');

    Http::assertSent(fn($request) => str_contains($request->url(), 'customer_id=cus_1'));
});

it('lists orders', function () {
    fakePolarList('v1/orders/*', [
        polarFixture('Order', ['id' => 'order_1', 'paid' => true]),
    ]);

    $page = LaravelPolar::listOrders(['subscription_id' => 'sub_1']);

    expect($page)->toHaveCount(1)
        ->and($page->first())->toBeInstanceOf(Data\Order::class)
        ->and($page->first()->id)->toBe('order_1')
        ->and($page->first()->paid)->toBeTrue();

    Http::assertSent(fn($request) => str_contains($request->url(), 'subscription_id=sub_1'));
});

it('gets a product', function () {
    fakePolar('v1/products/prod_1', polarFixture('Product', ['id' => 'prod_1', 'name' => 'Pro']));

    $product = LaravelPolar::getProduct('prod_1');

    expect($product)->toBeInstanceOf(Data\Product::class)
        ->and($product->id)->toBe('prod_1')
        ->and($product->name)->toBe('Pro');

    Http::assertSent(fn($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/v1/products/prod_1'));
});

it('lists meters', function () {
    fakePolarList('v1/meters/*', [meterFixture(['id' => 'meter_1'])]);

    $page = LaravelPolar::listMeters(['organization_id' => 'org_1']);

    expect($page)->toHaveCount(1)
        ->and($page->first())->toBeInstanceOf(Data\Meter::class)
        ->and($page->first()->id)->toBe('meter_1')
        ->and($page->first()->aggregation)->toBeInstanceOf(Data\CountAggregation::class);

    Http::assertSent(fn($request) => str_contains($request->url(), 'organization_id=org_1'));
});

it('gets a meter', function () {
    fakePolar('v1/meters/meter_1', meterFixture());

    $meter = LaravelPolar::getMeter('meter_1');

    expect($meter)->toBeInstanceOf(Data\Meter::class)
        ->and($meter->id)->toBe('meter_1')
        ->and($meter->name)->toBe('API calls');

    Http::assertSent(fn($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/v1/meters/meter_1'));
});

it('creates a meter', function () {
    fakePolar('v1/meters/', meterFixture(['id' => 'meter_new']), 201);

    $meter = LaravelPolar::createMeter([
        'name' => 'API calls',
        'unit' => 'scalar',
        'filter' => ['conjunction' => 'and', 'clauses' => []],
        'aggregation' => ['func' => 'count'],
        'organization_id' => 'org_1',
    ]);

    expect($meter->id)->toBe('meter_new');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/v1/meters/')
            && $request['name'] === 'API calls'
            && $request['aggregation'] === ['func' => 'count'];
    });
});

it('updates a meter', function () {
    fakePolar('v1/meters/meter_1', meterFixture(['name' => 'Renamed']));

    $meter = LaravelPolar::updateMeter('meter_1', ['name' => 'Renamed']);

    expect($meter->name)->toBe('Renamed');

    Http::assertSent(fn($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/v1/meters/meter_1')
        && $request['name'] === 'Renamed');
});

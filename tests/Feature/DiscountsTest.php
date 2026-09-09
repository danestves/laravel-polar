<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Support\Facades\Http;

it('creates a discount', function () {
    fakePolar('v1/discounts/', polarFixture('DiscountFixedOnceForeverDuration', [
        'id' => 'disc_1',
        'name' => 'Launch',
    ]), 201);

    $discount = LaravelPolar::createDiscount([
        'name' => 'Launch',
        'type' => 'fixed',
        'duration' => 'once',
        'amount' => 500,
        'currency' => 'usd',
        'organization_id' => 'org_1',
    ]);

    expect($discount)->toBeInstanceOf(Data\DiscountFixedOnceForeverDuration::class)
        ->and($discount->id)->toBe('disc_1');

    Http::assertSent(fn($request) => $request->method() === 'POST' && $request['name'] === 'Launch');
});

it('morphs a discount onto the right class from its type and duration', function (string $type, string $duration, string $expected) {
    fakePolar('v1/discounts/disc_1', polarFixture('DiscountFixedOnceForeverDuration', [
        'id' => 'disc_1',
        'type' => $type,
        'duration' => $duration,
    ] + ($duration === 'repeating' ? ['duration_in_months' => 3] : [])
      + ($type === 'percentage' ? ['basis_points' => 1000] : ['amount' => 500, 'currency' => 'usd'])));

    expect(LaravelPolar::getDiscount('disc_1'))->toBeInstanceOf($expected);
})->with([
    ['fixed', 'once', Data\DiscountFixedOnceForeverDuration::class],
    ['fixed', 'forever', Data\DiscountFixedOnceForeverDuration::class],
    ['fixed', 'repeating', Data\DiscountFixedRepeatDuration::class],
    ['percentage', 'once', Data\DiscountPercentageOnceForeverDuration::class],
    ['percentage', 'repeating', Data\DiscountPercentageRepeatDuration::class],
]);

it('updates a discount', function () {
    fakePolar('v1/discounts/disc_1', polarFixture('DiscountFixedOnceForeverDuration', [
        'id' => 'disc_1',
        'name' => 'Renamed',
    ]));

    expect(LaravelPolar::updateDiscount('disc_1', ['name' => 'Renamed'])->name)->toBe('Renamed');

    Http::assertSent(fn($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/v1/discounts/disc_1'));
});

it('deletes a discount', function () {
    fakePolar('v1/discounts/disc_1', [], 204);

    LaravelPolar::deleteDiscount('disc_1');

    Http::assertSent(fn($request) => $request->method() === 'DELETE');
});

it('lists discounts', function () {
    fakePolarList('v1/discounts/*', [
        polarFixture('DiscountFixedOnceForeverDuration', ['id' => 'disc_1']),
        polarFixture('DiscountFixedOnceForeverDuration', ['id' => 'disc_2']),
    ]);

    expect(LaravelPolar::listDiscounts()->collect()->pluck('id')->all())
        ->toBe(['disc_1', 'disc_2']);
});

it('hydrates a non-null nested discount on Checkout, CheckoutLink, Order and Subscription', function () {
    $checkoutDiscount = [
        'id' => 'disc_1',
        'name' => '10% off',
        'code' => 'SAVE10',
        'type' => 'percentage',
        'duration' => 'once',
        'basis_points' => 1000,
    ];

    $checkout = Data\Checkout::from(polarFixture('Checkout', ['discount' => $checkoutDiscount]));

    expect($checkout->discount)->toBeInstanceOf(Data\CheckoutDiscountPercentageOnceForeverDuration::class)
        ->and($checkout->discount->basisPoints)->toBe(1000);

    $baseDiscount = $checkoutDiscount + [
        'created_at' => '2026-01-01T00:00:00Z',
        'modified_at' => null,
        'metadata' => [],
        'starts_at' => null,
        'ends_at' => null,
        'max_redemptions' => null,
        'max_redemptions_per_customer' => null,
        'redemptions_count' => 0,
        'organization_id' => 'org_1',
    ];

    $order = Data\Order::from(polarFixture('Order', ['discount' => $baseDiscount]));
    $subscription = Data\Subscription::from(polarFixture('Subscription', ['discount' => $baseDiscount]));
    $checkoutLink = Data\CheckoutLink::from(polarFixture('CheckoutLink', ['discount' => $baseDiscount]));

    expect($order->discount)->toBeInstanceOf(Data\DiscountPercentageOnceForeverDurationBase::class)
        ->and($subscription->discount)->toBeInstanceOf(Data\DiscountPercentageOnceForeverDurationBase::class)
        ->and($checkoutLink->discount)->toBeInstanceOf(Data\DiscountPercentageOnceForeverDurationBase::class);
});

it('hydrates a null nested discount on Checkout, CheckoutLink, Order and Subscription', function () {
    expect(Data\Checkout::from(polarFixture('Checkout', ['discount' => null]))->discount)->toBeNull()
        ->and(Data\Order::from(polarFixture('Order', ['discount' => null]))->discount)->toBeNull()
        ->and(Data\Subscription::from(polarFixture('Subscription', ['discount' => null]))->discount)->toBeNull()
        ->and(Data\CheckoutLink::from(polarFixture('CheckoutLink', ['discount' => null]))->discount)->toBeNull();
});

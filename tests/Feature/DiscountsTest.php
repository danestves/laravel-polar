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

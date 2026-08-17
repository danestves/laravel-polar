<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Enums\RefundCreateReason;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Order;
use Illuminate\Support\Facades\Http;

it('creates a refund', function () {
    fakePolar('v1/refunds/', polarFixture('Refund', ['id' => 'ref_1', 'amount' => 5000]), 201);

    $refund = LaravelPolar::createRefund([
        'order_id' => 'order_1',
        'reason' => RefundCreateReason::CustomerRequest->value,
        'amount' => 5000,
    ]);

    expect($refund)->toBeInstanceOf(Data\Refund::class)
        ->and($refund->amount)->toBe(5000);

    Http::assertSent(fn($request) => $request->method() === 'POST'
        && $request['order_id'] === 'order_1'
        && $request['reason'] === 'customer_request');
});

it('lists refunds', function () {
    fakePolarList('v1/refunds/*', [polarFixture('Refund', ['id' => 'ref_1'])]);

    expect(LaravelPolar::listRefunds()->first()->id)->toBe('ref_1');
});

it('refunds an order, defaulting to the unrefunded remainder', function () {
    fakePolar('v1/refunds/', polarFixture('Refund', ['id' => 'ref_1']), 201);

    $order = Order::factory()->paid()->create([
        'polar_id' => 'order_1',
        'amount' => 10000,
        'refunded_amount' => 2500,
    ]);

    $order->refund();

    Http::assertSent(fn($request) => $request['order_id'] === 'order_1'
        && $request['amount'] === 7500
        && $request['reason'] === 'customer_request');
});

it('refunds an order with an explicit amount, reason, comment and metadata', function () {
    fakePolar('v1/refunds/', polarFixture('Refund', ['id' => 'ref_1']), 201);

    $order = Order::factory()->paid()->create(['polar_id' => 'order_1', 'amount' => 10000]);

    $order->refund(
        amount: 1000,
        reason: RefundCreateReason::Duplicate,
        comment: 'Charged twice',
        metadata: ['ticket' => 'SUP-42'],
    );

    Http::assertSent(fn($request) => $request['amount'] === 1000
        && $request['reason'] === 'duplicate'
        && $request['comment'] === 'Charged twice'
        && $request['metadata'] === ['ticket' => 'SUP-42']);
});

it('refuses to refund an order that was never synced', function () {
    $order = Order::factory()->paid()->create(['polar_id' => null]);

    expect(fn() => $order->refund())->toThrow(\RuntimeException::class, 'cannot refund');
});

it('lists the refunds belonging to an order', function () {
    fakePolarList('v1/refunds/*', [
        polarFixture('Refund', ['id' => 'ref_1']),
        polarFixture('Refund', ['id' => 'ref_2']),
    ]);

    $order = Order::factory()->paid()->create(['polar_id' => 'order_1']);

    expect($order->refunds()->pluck('id')->all())->toBe(['ref_1', 'ref_2']);

    Http::assertSent(fn($request) => str_contains($request->url(), 'order_id=order_1'));
});

it('returns no refunds for an order that was never synced', function () {
    $order = Order::factory()->paid()->create(['polar_id' => null]);

    expect($order->refunds())->toBeEmpty();

    Http::assertNothingSent();
});

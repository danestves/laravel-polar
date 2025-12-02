<?php

use Danestves\LaravelPolar\Enums\OrderStatus;
use Danestves\LaravelPolar\Order as PolarOrder;
use Danestves\LaravelPolar\Tests\Fixtures\User;

beforeEach(function () {
    \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
        'users' => User::class,
    ]);
});

it('can determine if the order is paid', function () {
    $order = new Order(['status' => OrderStatus::Paid]);

    expect($order->paid())->toBeTrue();
    expect($order->refunded())->toBeFalse();
});

it('can determine if the order is refunded', function () {
    $order = new Order([
        'status' => OrderStatus::Refunded,
        'refunded_amount' => 1000,
        'refunded_tax_amount' => 100,
        'refunded_at' => now()->subDay(),
    ]);

    expect($order->refunded())->toBeTrue();
    expect($order->paid())->toBeFalse();
    expect($order->partiallyRefunded())->toBeFalse();
});

it('can determine if the order is partially refunded', function () {
    $order = new Order([
        'status' => OrderStatus::PartiallyRefunded,
        'refunded_amount' => 1000,
        'refunded_tax_amount' => 100,
        'refunded_at' => now()->subDay(),
    ]);

    expect($order->partiallyRefunded())->toBeTrue();
    expect($order->paid())->toBeFalse();
    expect($order->refunded())->toBeFalse();
});

it('can determine if the order is for a specific product', function () {
    $order = new Order(['product_id' => '45067']);

    expect($order->hasProduct('45067'))->toBeTrue();
    expect($order->hasProduct('93048'))->toBeFalse();
});

it('can filter orders by paid scope', function () {
    Order::factory()->paid()->count(3)->create();
    Order::factory()->refunded()->count(2)->create();

    $paidOrders = Order::query()->paid()->get();

    expect($paidOrders)->toHaveCount(3);
    $paidOrders->each(fn($order) => expect($order->status)->toBe(OrderStatus::Paid));
});

it('can filter orders by refunded scope', function () {
    Order::factory()->paid()->count(2)->create();
    Order::factory()->refunded()->count(3)->create();

    $refundedOrders = Order::query()->refunded()->get();

    expect($refundedOrders)->toHaveCount(3);
    $refundedOrders->each(fn($order) => expect($order->status)->toBe(OrderStatus::Refunded));
});

it('can filter orders by partially refunded scope', function () {
    Order::factory()->paid()->count(2)->create();
    Order::factory()->partiallyRefunded()->count(2)->create();

    $partiallyRefundedOrders = Order::query()->partiallyRefunded()->get();

    expect($partiallyRefundedOrders)->toHaveCount(2);
    $partiallyRefundedOrders->each(fn($order) => expect($order->status)->toBe(OrderStatus::PartiallyRefunded));
});

it('can sync order data', function () {
    $order = Order::factory()->paid()->create([
        'polar_id' => 'order_123',
        'product_id' => 'product_123',
        'amount' => 1000,
    ]);

    $order->sync([
        'id' => 'order_123',
        'status' => OrderStatus::Refunded->value,
        'amount' => 1000,
        'tax_amount' => 100,
        'refunded_amount' => 1000,
        'refunded_tax_amount' => 100,
        'currency' => 'USD',
        'billing_reason' => 'purchase',
        'customer_id' => 'customer_123',
        'product_id' => 'product_456',
        'refunded_at' => now()->toIso8601String(),
        'created_at' => now()->toIso8601String(),
    ]);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Refunded);
    expect($order->product_id)->toBe('product_456');
    expect($order->refunded_amount)->toBe(1000);
    expect($order->refunded_at)->not->toBeNull();
});

it('can access billable relationship', function () {
    $user = User::factory()->create();
    $order = Order::factory()->paid()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
    ]);

    expect($order->billable)->toBeInstanceOf(User::class);
    expect($order->billable->getKey())->toBe($user->getKey());
});

class Order extends PolarOrder
{
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';
}

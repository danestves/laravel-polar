<?php

use Danestves\LaravelPolar\Order as PolarOrder;
use Danestves\LaravelPolar\Enums\OrderStatus;
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

it('ignores a webhook that is older than the state it already holds', function () {
    $order = Order::factory()->paid()->create([
        'polar_id' => 'order_stale',
        'product_id' => 'prod_new',
        'polar_updated_at_us' => (int) now()->getPreciseTimestamp(6),
    ]);

    $order->sync([
        'id' => 'order_stale',
        'status' => OrderStatus::Refunded->value,
        'net_amount' => 1000,
        'tax_amount' => 100,
        'refunded_amount' => 1000,
        'refunded_tax_amount' => 100,
        'currency' => 'USD',
        'billing_reason' => 'purchase',
        'customer_id' => 'customer_123',
        'product_id' => 'prod_old',
        'created_at' => now()->toIso8601String(),
        'modified_at' => now()->subDay()->toIso8601String(),
    ]);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Paid);
    expect($order->product_id)->toBe('prod_new');
});

it('compares ordering watermarks in UTC when the app timezone is not UTC', function () {
    $original = date_default_timezone_get();
    date_default_timezone_set('America/New_York');

    try {
        $order = Order::factory()->paid()->create([
            'polar_id' => 'order_tz',
            'polar_updated_at_us' => null,
        ]);

        $order->sync([
            'id' => 'order_tz',
            'status' => OrderStatus::Pending->value,
            'net_amount' => 1000,
            'tax_amount' => 100,
            'refunded_amount' => 0,
            'refunded_tax_amount' => 0,
            'currency' => 'USD',
            'billing_reason' => 'purchase',
            'customer_id' => 'customer_123',
            'product_id' => 'product_123',
            'created_at' => '2026-01-01T11:00:00Z',
            'modified_at' => null,
        ], new \DateTimeImmutable('2026-01-01T12:00:00Z'));

        $order->sync([
            'id' => 'order_tz',
            'status' => OrderStatus::Paid->value,
            'net_amount' => 1000,
            'tax_amount' => 100,
            'refunded_amount' => 0,
            'refunded_tax_amount' => 0,
            'currency' => 'USD',
            'billing_reason' => 'purchase',
            'customer_id' => 'customer_123',
            'product_id' => 'product_123',
            'created_at' => '2026-01-01T11:00:00Z',
            'modified_at' => '2026-01-01T12:00:30Z',
        ]);

        expect($order->fresh()->status)->toBe(OrderStatus::Paid);
    } finally {
        date_default_timezone_set($original);
    }
});

it('rejects a delayed created event within the same second', function () {
    $order = Order::factory()->paid()->create([
        'polar_id' => 'order_microseconds',
        'polar_updated_at_us' => null,
    ]);

    $order->sync([
        'id' => 'order_microseconds',
        'status' => OrderStatus::Paid->value,
        'net_amount' => 1000,
        'tax_amount' => 100,
        'refunded_amount' => 0,
        'refunded_tax_amount' => 0,
        'currency' => 'USD',
        'billing_reason' => 'purchase',
        'customer_id' => 'customer_123',
        'product_id' => 'product_123',
        'created_at' => '2026-01-01T11:00:00Z',
        'modified_at' => null,
    ], new \DateTimeImmutable('2026-01-01T12:00:00.900000Z'));

    $order->sync([
        'id' => 'order_microseconds',
        'status' => OrderStatus::Pending->value,
        'net_amount' => 1000,
        'tax_amount' => 100,
        'refunded_amount' => 0,
        'refunded_tax_amount' => 0,
        'currency' => 'USD',
        'billing_reason' => 'purchase',
        'customer_id' => 'customer_123',
        'product_id' => 'product_123',
        'created_at' => '2026-01-01T11:00:00Z',
        'modified_at' => null,
    ], new \DateTimeImmutable('2026-01-01T12:00:00.100000Z'));

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
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

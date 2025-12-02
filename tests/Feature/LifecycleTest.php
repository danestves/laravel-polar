<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Customer;
use Polar\Models\Components\OrderStatus;
use Polar\Models\Components\SubscriptionStatus;
use Danestves\LaravelPolar\Order;
use Danestves\LaravelPolar\Subscription;
use Danestves\LaravelPolar\Tests\Fixtures\User;

beforeEach(function () {
    \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
        'users' => User::class,
    ]);
});

it('can create customer and then create subscription', function () {
    $user = User::factory()->create();
    $customer = $user->createAsCustomer();

    expect($customer)->toBeInstanceOf(Customer::class);
    expect($customer->billable_id)->toBe($user->getKey());

    $subscription = Subscription::factory()->active()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
    ]);

    expect($subscription->billable_id)->toBe($user->getKey());
    expect($user->subscribed())->toBeTrue();
});

it('can create order for customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
    ]);

    $order = Order::factory()->paid()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'product_id' => 'product_123',
    ]);

    expect($order->billable_id)->toBe($user->getKey());
    expect($user->hasPurchasedProduct('product_123'))->toBeTrue();
});

it('can track subscription lifecycle from active to cancelled', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->active()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
    ]);

    expect($subscription->status)->toBe(SubscriptionStatus::Active);
    expect($subscription->valid())->toBeTrue();

    $subscription->update([
        'status' => SubscriptionStatus::Canceled,
        'ends_at' => now()->addDays(5),
    ]);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Canceled);
    expect($subscription->valid())->toBeTrue();
    expect($subscription->onGracePeriod())->toBeTrue();

    $subscription->update([
        'ends_at' => now()->subDays(5),
    ]);

    $subscription->refresh();
    expect($subscription->valid())->toBeFalse();
    expect($subscription->onGracePeriod())->toBeFalse();
});

it('can track order lifecycle from paid to refunded', function () {
    $user = User::factory()->create();
    $order = Order::factory()->paid()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'amount' => 1000,
        'refunded_amount' => 0,
    ]);

    expect($order->status)->toBe(OrderStatus::Paid);
    expect($order->paid())->toBeTrue();
    expect($order->refunded())->toBeFalse();

    $order->update([
        'status' => OrderStatus::PartiallyRefunded->value,
        'refunded_amount' => 500,
        'refunded_at' => now(),
    ]);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::PartiallyRefunded);
    expect($order->partiallyRefunded())->toBeTrue();
    expect($order->refunded_amount)->toBe(500);

    $order->update([
        'status' => OrderStatus::Refunded->value,
        'refunded_amount' => 1000,
    ]);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Refunded);
    expect($order->refunded())->toBeTrue();
});

it('can have multiple subscriptions for different products', function () {
    $user = User::factory()->create();

    $subscription1 = Subscription::factory()->active()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'product_id' => 'product_123',
        'type' => 'premium',
    ]);

    $subscription2 = Subscription::factory()->active()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'product_id' => 'product_456',
        'type' => 'enterprise',
    ]);

    $user->refresh();

    expect($user->subscribed('premium', 'product_123'))->toBeTrue();
    expect($user->subscribed('enterprise', 'product_456'))->toBeTrue();
    expect($user->subscribed('premium', 'product_456'))->toBeFalse();
    expect($user->subscribedToProduct('product_123', 'premium'))->toBeTrue();
    expect($user->subscribedToProduct('product_456', 'enterprise'))->toBeTrue();
});

it('can have multiple orders for different products', function () {
    $user = User::factory()->create();

    $order1 = Order::factory()->paid()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'product_id' => 'product_123',
    ]);

    $order2 = Order::factory()->paid()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'product_id' => 'product_456',
    ]);

    expect($user->hasPurchasedProduct('product_123'))->toBeTrue();
    expect($user->hasPurchasedProduct('product_456'))->toBeTrue();
    expect($user->hasPurchasedProduct('product_789'))->toBeFalse();
});

it('can access all subscriptions through billable relationship', function () {
    $user = User::factory()->create();

    Subscription::factory()->active()->count(3)->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
    ]);

    $user->refresh();
    expect($user->subscriptions)->toHaveCount(3);
    $user->subscriptions->each(fn($sub) => expect($sub->status)->toBe(SubscriptionStatus::Active));
});

it('can access all orders through billable relationship', function () {
    $user = User::factory()->create();

    Order::factory()->paid()->count(3)->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
    ]);

    $user->refresh();
    expect($user->orders)->toHaveCount(3);
    $user->orders->each(fn($order) => expect($order->status)->toBe(OrderStatus::Paid));
});

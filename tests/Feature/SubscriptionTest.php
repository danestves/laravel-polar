<?php

use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Polar\Models\Components\SubscriptionStatus;
use Danestves\LaravelPolar\Subscription;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('polar.access_token', 'test-token');
    Config::set('polar.server', 'sandbox');
});

afterEach(function () {
    Mockery::close();
});

it('can determine if the subscription is valid while on its grace period', function () {
    $subscription = Subscription::factory()->cancelled()->create([
        'ends_at' => now()->addDays(5),
    ]);

    expect($subscription->valid())->toBeTrue();

    $subscription = Subscription::factory()->cancelled()->create([
        'ends_at' => now()->subDays(5),
    ]);

    expect($subscription->valid())->toBeFalse();
});

it('can determine if subscription is on grace period', function () {
    $subscription = Subscription::factory()->cancelled()->create([
        'ends_at' => now()->addDays(5),
    ]);

    expect($subscription->onGracePeriod())->toBeTrue();

    $subscription = Subscription::factory()->cancelled()->create([
        'ends_at' => now()->subDays(5),
    ]);

    expect($subscription->onGracePeriod())->toBeFalse();
});

it('can determine if subscription has expired trial', function () {
    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->subDays(5),
    ]);

    expect($subscription->hasExpiredTrial())->toBeTrue();

    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDays(5),
    ]);

    expect($subscription->hasExpiredTrial())->toBeFalse();
});

it('can filter subscriptions by active scope', function () {
    Subscription::factory()->active()->count(3)->create();
    Subscription::factory()->cancelled()->count(2)->create();

    $activeSubscriptions = Subscription::query()->active()->get();

    expect($activeSubscriptions)->toHaveCount(3);
    $activeSubscriptions->each(fn($sub) => expect($sub->status)->toBe(SubscriptionStatus::Active));
});

it('can filter subscriptions by cancelled scope', function () {
    Subscription::factory()->active()->count(2)->create();
    Subscription::factory()->cancelled()->count(3)->create();

    $cancelledSubscriptions = Subscription::query()->cancelled()->get();

    expect($cancelledSubscriptions)->toHaveCount(3);
    $cancelledSubscriptions->each(fn($sub) => expect($sub->status)->toBe(SubscriptionStatus::Canceled));
});

it('can filter subscriptions by on trial scope', function () {
    Subscription::factory()->active()->count(2)->create();
    Subscription::factory()->trialing()->create();

    $trialingSubscriptions = Subscription::query()->onTrial()->get();

    expect($trialingSubscriptions)->toHaveCount(1);
    expect($trialingSubscriptions->first()->status)->toBe(SubscriptionStatus::Trialing);
});

it('can filter subscriptions by past due scope', function () {
    Subscription::factory()->active()->count(2)->create();
    Subscription::factory()->pastDue()->count(2)->create();

    $pastDueSubscriptions = Subscription::query()->pastDue()->get();

    expect($pastDueSubscriptions)->toHaveCount(2);
    $pastDueSubscriptions->each(fn($sub) => expect($sub->status)->toBe(SubscriptionStatus::PastDue));
});

it('can filter subscriptions by unpaid scope', function () {
    Subscription::factory()->active()->count(2)->create();
    Subscription::factory()->unpaid()->count(2)->create();

    $unpaidSubscriptions = Subscription::query()->unpaid()->get();

    expect($unpaidSubscriptions)->toHaveCount(2);
    $unpaidSubscriptions->each(fn($sub) => expect($sub->status)->toBe(SubscriptionStatus::Unpaid));
});

it('can filter subscriptions by incomplete scope', function () {
    Subscription::factory()->active()->count(2)->create();
    Subscription::factory()->incomplete()->create();

    $incompleteSubscriptions = Subscription::query()->incomplete()->get();

    expect($incompleteSubscriptions)->toHaveCount(1);
    expect($incompleteSubscriptions->first()->status)->toBe(SubscriptionStatus::Incomplete);
});

it('can filter subscriptions by incomplete expired scope', function () {
    Subscription::factory()->active()->count(2)->create();
    Subscription::factory()->incompleteExpired()->count(2)->create();

    $incompleteExpiredSubscriptions = Subscription::query()->incompleteExpired()->get();

    expect($incompleteExpiredSubscriptions)->toHaveCount(2);
    $incompleteExpiredSubscriptions->each(fn($sub) => expect($sub->status)->toBe(SubscriptionStatus::IncompleteExpired));
});

it('can sync subscription data', function () {
    $subscription = Subscription::factory()->active()->create([
        'product_id' => 'product_123',
    ]);

    $subscription->sync([
        'status' => SubscriptionStatus::PastDue->value,
        'product_id' => 'product_456',
        'current_period_end' => now()->addDays(30)->toIso8601String(),
        'ends_at' => null,
    ]);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::PastDue);
    expect($subscription->product_id)->toBe('product_456');
    expect($subscription->current_period_end)->not->toBeNull();
});

it('throws exception when resuming incomplete expired subscription', function () {
    $subscription = Subscription::factory()->incompleteExpired()->create([
        'polar_id' => 'polar_sub_123',
    ]);

    expect(fn() => $subscription->resume())
        ->toThrow(PolarApiError::class, 'Subscription is incomplete and expired.');
});

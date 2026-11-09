<?php

use Danestves\LaravelPolar\Enums\CustomerCancellationReason;
use Danestves\LaravelPolar\Enums\SubscriptionStatus;
use Danestves\LaravelPolar\Subscription;
use Illuminate\Support\Facades\Http;

it('pauses a subscription at the end of the period', function () {
    fakePolar('v1/subscriptions/*', polarFixture('Subscription', [
        'id' => 'sub_pause',
        'status' => 'active',
        'pause_at_period_end' => true,
        'current_period_end' => now()->addDays(10)->toIso8601String(),
    ]));

    $subscription = Subscription::factory()->active()->create([
        'polar_id' => 'sub_pause',
        'product_id' => 'prod_orig',
    ]);

    $resumesAt = now()->addDays(30);

    $subscription->pause($resumesAt);

    Http::assertSent(fn($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/v1/subscriptions/sub_pause')
        && $request['pause_at_period_end'] === true
        && $request['resumes_at'] !== null);

    expect($subscription->pause_at_period_end)->toBeTrue();
    expect($subscription->onPauseGracePeriod())->toBeTrue();
});

it('cancels a scheduled pause', function () {
    fakePolar('v1/subscriptions/*', polarFixture('Subscription', [
        'id' => 'sub_unpause',
        'status' => 'active',
        'pause_at_period_end' => false,
    ]));

    $subscription = Subscription::factory()->active()->create([
        'polar_id' => 'sub_unpause',
        'product_id' => 'prod_orig',
    ]);

    $subscription->unpause();

    Http::assertSent(fn($request) => $request->method() === 'PATCH'
        && $request['pause_at_period_end'] === false);

    expect($subscription->pause_at_period_end)->toBeFalse();
});

it('resumes a paused subscription immediately', function () {
    fakePolar('v1/subscriptions/*', polarFixture('Subscription', [
        'id' => 'sub_resume_paused',
        'status' => 'active',
        'pause_at_period_end' => false,
        'paused_at' => null,
    ]));

    $subscription = Subscription::factory()->create([
        'polar_id' => 'sub_resume_paused',
        'product_id' => 'prod_orig',
        'status' => SubscriptionStatus::Paused,
        'paused_at' => now()->subDay(),
    ]);

    $subscription->resumePaused();

    Http::assertSent(fn($request) => $request->method() === 'PATCH'
        && $request['resume'] === true);

    expect($subscription->status)->toBe(SubscriptionStatus::Active);
});

it('revokes a subscription immediately', function () {
    fakePolar('v1/subscriptions/*', polarFixture('Subscription', [
        'id' => 'sub_revoke',
        'status' => 'canceled',
        'ends_at' => now()->toIso8601String(),
    ]));

    $subscription = Subscription::factory()->active()->create([
        'polar_id' => 'sub_revoke',
        'product_id' => 'prod_orig',
    ]);

    $subscription->cancelNow(CustomerCancellationReason::TooExpensive, 'too pricey');

    Http::assertSent(fn($request) => $request->method() === 'PATCH'
        && $request['revoke'] === true
        && $request['customer_cancellation_reason'] === CustomerCancellationReason::TooExpensive->value
        && $request['customer_cancellation_comment'] === 'too pricey');

    expect($subscription->status)->toBe(SubscriptionStatus::Canceled);
    expect($subscription->ends_at)->not->toBeNull();
});

it('exposes a paused scope and status helper', function () {
    $paused = Subscription::factory()->create(['status' => SubscriptionStatus::Paused]);
    Subscription::factory()->active()->create();

    expect($paused->paused())->toBeTrue();
    expect(Subscription::query()->paused()->count())->toBe(1);
});

it('persists the full lifecycle payload from a webhook sync', function () {
    $subscription = Subscription::factory()->active()->create([
        'polar_id' => 'sub_sync',
        'product_id' => 'prod_old',
    ]);

    $subscription->sync([
        'id' => 'sub_sync',
        'status' => 'paused',
        'product_id' => 'prod_new',
        'discount_id' => 'disc_123',
        'current_period_start' => now()->subDays(15)->toIso8601String(),
        'current_period_end' => now()->addDays(15)->toIso8601String(),
        'cancel_at_period_end' => true,
        'canceled_at' => now()->subDay()->toIso8601String(),
        'ends_at' => now()->addDays(15)->toIso8601String(),
        'pause_at_period_end' => true,
        'paused_at' => now()->toIso8601String(),
        'resumes_at' => now()->addDays(5)->toIso8601String(),
        'started_at' => now()->subYear()->toIso8601String(),
        'past_due_at' => now()->subDays(3)->toIso8601String(),
        'modified_at' => now()->toIso8601String(),
    ]);

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Paused);
    expect($subscription->product_id)->toBe('prod_new');
    expect($subscription->discount_id)->toBe('disc_123');
    expect($subscription->cancel_at_period_end)->toBeTrue();
    expect($subscription->canceled_at)->not->toBeNull();
    expect($subscription->pause_at_period_end)->toBeTrue();
    expect($subscription->paused_at)->not->toBeNull();
    expect($subscription->resumes_at)->not->toBeNull();
    expect($subscription->started_at)->not->toBeNull();
    expect($subscription->past_due_at)->not->toBeNull();
    expect($subscription->polar_updated_at_us)->not->toBeNull();
});

it('ignores a webhook that is older than the state it already holds', function () {
    $subscription = Subscription::factory()->active()->create([
        'polar_id' => 'sub_stale',
        'product_id' => 'prod_new',
        'polar_updated_at_us' => (int) now()->getPreciseTimestamp(6),
    ]);

    $subscription->sync([
        'id' => 'sub_stale',
        'status' => 'canceled',
        'product_id' => 'prod_old',
        'modified_at' => now()->subDay()->toIso8601String(),
    ]);

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Active);
    expect($subscription->product_id)->toBe('prod_new');
});

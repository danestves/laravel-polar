<?php

use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Danestves\LaravelPolar\Subscription;
use Danestves\LaravelPolar\Enums\SubscriptionProrationBehavior;
use Danestves\LaravelPolar\Enums\SubscriptionStatus;
use Illuminate\Support\Facades\Http;

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

it('can sync subscription data with trial end', function () {
    $subscription = Subscription::factory()->active()->create([
        'product_id' => 'product_123',
    ]);

    $trialEnd = now()->addDays(14)->toIso8601String();

    $subscription->sync([
        'status' => SubscriptionStatus::Trialing->value,
        'product_id' => 'product_123',
        'current_period_end' => now()->addDays(30)->toIso8601String(),
        'trial_end' => $trialEnd,
        'ends_at' => null,
    ]);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Trialing);
    expect($subscription->trial_ends_at)->not->toBeNull();
    expect($subscription->trial_ends_at->toIso8601String())->toBe($trialEnd);
});

it('throws exception when resuming incomplete expired subscription', function () {
    $subscription = Subscription::factory()->incompleteExpired()->create([
        'polar_id' => 'polar_sub_123',
    ]);

    expect(fn() => $subscription->resume())
        ->toThrow(PolarApiError::class, 'Subscription is incomplete and expired.');
});

it('applies a discount to the subscription', function () {
    fakePolar('v1/subscriptions/*', polarFixture('Subscription', [
        'id' => 'sub_apply',
        'status' => 'active',
        'product_id' => 'prod_orig',
    ]));

    $subscription = Subscription::factory()->active()->create([
        'polar_id' => 'sub_apply',
        'product_id' => 'prod_orig',
    ]);

    expect($subscription->applyDiscount('disc_holiday'))->toBe($subscription);

    Http::assertSent(fn($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/v1/subscriptions/sub_apply')
        && $request['discount_id'] === 'disc_holiday');
});

it('removes a discount by sending an explicit null', function () {
    fakePolar('v1/subscriptions/*', polarFixture('Subscription', [
        'id' => 'sub_remove',
        'status' => 'active',
    ]));

    Subscription::factory()->active()->create([
        'polar_id' => 'sub_remove',
        'product_id' => 'prod_orig',
    ])->removeDiscount();

    // Polar reads an omitted key as "leave alone", so clearing requires the null to survive.
    Http::assertSent(fn($request) => array_key_exists('discount_id', $request->data())
        && $request['discount_id'] === null);
});

it('swaps the subscription onto another product', function () {
    fakePolar('v1/subscriptions/*', polarFixture('Subscription', [
        'id' => 'sub_swap',
        'status' => 'active',
        'product_id' => 'prod_new',
    ]));

    $subscription = Subscription::factory()->active()->create([
        'polar_id' => 'sub_swap',
        'product_id' => 'prod_old',
    ]);

    $subscription->swap('prod_new');

    expect($subscription->refresh()->product_id)->toBe('prod_new');

    Http::assertSent(fn($request) => $request['product_id'] === 'prod_new'
        && $request['proration_behavior'] === SubscriptionProrationBehavior::Prorate->value);
});

it('invoices immediately when swapping and invoicing', function () {
    fakePolar('v1/subscriptions/*', polarFixture('Subscription', ['status' => 'active']));

    Subscription::factory()->active()->create(['polar_id' => 'sub_swap'])
        ->swapAndInvoice('prod_new');

    Http::assertSent(fn($request) => $request['proration_behavior'] === SubscriptionProrationBehavior::Invoice->value);
});

it('cancels at period end and uncancels by resuming', function () {
    fakePolar('v1/subscriptions/*', polarFixture('Subscription', ['status' => 'active']));

    $subscription = Subscription::factory()->active()->create(['polar_id' => 'sub_1']);

    $subscription->cancel();
    $subscription->resume();

    $sent = [];
    Http::assertSent(function ($request) use (&$sent) {
        $sent[] = $request['cancel_at_period_end'];

        return true;
    });

    expect($sent)->toBe([true, false]);
});

it('sets a trial end date on the subscription', function () {
    fakePolar('v1/subscriptions/*', polarFixture('Subscription', ['status' => 'trialing']));

    Subscription::factory()->active()->create(['polar_id' => 'sub_1'])
        ->updateTrial(new \DateTimeImmutable('2026-06-01T00:00:00+00:00'));

    Http::assertSent(fn($request) => str_starts_with($request['trial_end'], '2026-06-01T00:00:00'));
});

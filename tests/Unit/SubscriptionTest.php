<?php

use Danestves\LaravelPolar\Enums\SubscriptionStatus;
use Danestves\LaravelPolar\Subscription;

it('can determine if the subscription is on trial', function () {
    $subscription = new Subscription(['status' => SubscriptionStatus::Trialing]);

    expect($subscription->onTrial())->toBeTrue();
    expect($subscription->active())->toBeFalse();
});

it('can determine if the subscription is active', function () {
    $subscription = new Subscription(['status' => SubscriptionStatus::Active]);

    expect($subscription->active())->toBeTrue();
    expect($subscription->cancelled())->toBeFalse();
});

it('can determine if the subscription is past due', function () {
    $subscription = new Subscription(['status' => SubscriptionStatus::PastDue]);

    expect($subscription->pastDue())->toBeTrue();
    expect($subscription->active())->toBeFalse();
});

it('can determine if the subscription is unpaid', function () {
    $subscription = new Subscription(['status' => SubscriptionStatus::Unpaid]);

    expect($subscription->unpaid())->toBeTrue();
    expect($subscription->active())->toBeFalse();
});

it('can determine if the subscription is cancelled', function () {
    $subscription = new Subscription(['status' => SubscriptionStatus::Canceled]);

    expect($subscription->cancelled())->toBeTrue();
    expect($subscription->active())->toBeFalse();
});

it('can determine if the subscription is on a specific product', function () {
    $subscription = new Subscription(['product_id' => '45067']);

    expect($subscription->hasProduct('45067'))->toBeTrue();
    expect($subscription->hasProduct('93048'))->toBeFalse();
});

it('can determine if the subscription is on a specific price', function () {
    $subscription = new Subscription(['price_id' => '45067']);

    expect($subscription->hasPrice('45067'))->toBeTrue();
    expect($subscription->hasPrice('93048'))->toBeFalse();
});

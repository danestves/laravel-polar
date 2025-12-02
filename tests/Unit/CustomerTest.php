<?php

use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Tests\Fixtures\User;

beforeEach(function () {
    \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
        'users' => User::class,
    ]);
});

it('can determine if the customer is on a generic trial', function () {
    $customer = Customer::factory()->create([
        'trial_ends_at' => now()->addDays(7),
    ]);

    expect($customer->onGenericTrial())->toBeTrue();
});

it('can determine if the customer has an expired generic trial', function () {
    $customer = Customer::factory()->create([
        'trial_ends_at' => now()->subDays(7),
    ]);

    expect($customer->hasExpiredGenericTrial())->toBeTrue();
});

it('returns false when trial_ends_at is null', function () {
    $customer = Customer::factory()->create([
        'trial_ends_at' => null,
    ]);

    expect($customer->onGenericTrial())->toBeFalse();
    expect($customer->hasExpiredGenericTrial())->toBeFalse();
});

it('can access billable relationship', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
    ]);

    expect($customer->billable)->toBeInstanceOf(User::class);
    expect($customer->billable->getKey())->toBe($user->getKey());
});

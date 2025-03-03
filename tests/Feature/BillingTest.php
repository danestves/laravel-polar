<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Checkout;
use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Mockery;

it('can generate a checkout for a billable', function () {
    $checkout = Mockery::mock(Checkout::class);
    $url = 'https://sandbox.polar.sh/checkout/polar_c_123';

    $checkout->shouldReceive('url')
        ->once()
        ->andReturn($url);

    $user = new User();
    $user = Mockery::mock($user);
    $user->shouldReceive('checkout')
        ->once()
        ->with(['product_123'])
        ->andReturn($checkout);

    $result = $user->checkout(['product_123']);

    expect($result->url())
        ->toBe($url);
});

it('can generate a checkout for a billable with metadata', function () {
    $checkout = Mockery::mock(Checkout::class);
    $url = 'https://sandbox.polar.sh/checkout/polar_c_123';

    $checkout->shouldReceive('url')
        ->once()
        ->andReturn($url);

    $user = new User();
    $user = Mockery::mock($user);
    $user->shouldReceive('checkout')
        ->once()
        ->with(
            ['product_123'],
            [],
            [],
            ['batch_id' => '789'],
        )
        ->andReturn($checkout);

    $result = $user->checkout(['product_123'], [], [], ['batch_id' => '789']);

    expect($result->url())
        ->toBe($url);
});

it('can not overwrite the customer id and type or subscription id for a billable', function () {
    $reservedKeywords = [
        'billable_id' => '123',
        'billable_type' => 'user',
        'subscription_type' => 'premium',
    ];

    foreach ($reservedKeywords as $key => $value) {
        $user = new User();
        $user = Mockery::mock($user);
        $user->shouldReceive('checkout')
            ->once()
            ->with(['product_123'], [], [$key => $value], [])
            ->andThrow(new \InvalidArgumentException(
                'You cannot use "billable_id", "billable_type" or "subscription_type" as custom data keys because these are reserved keywords.',
            ));

        expect(fn() => $user->checkout(['product_123'], [], [$key => $value], []))
            ->toThrow(
                \InvalidArgumentException::class,
                'You cannot use "billable_id", "billable_type" or "subscription_type" as custom data keys because these are reserved keywords.',
            );
    }
});

it('can generate a customer portal link for a billable', function () {
    $portalUrl = 'https://sandbox.polar.sh/test-slug/portal?customer_session_token=TOKEN';

    $user = new User();
    $user = Mockery::mock($user);
    $user->shouldReceive('customerPortalUrl')
        ->once()
        ->andReturn($portalUrl);

    $url = $user->customerPortalUrl();

    expect($url)->toBe($portalUrl);
});

it('can determine the generic trial on a billable', function () {
    $customer = Mockery::mock(Customer::class);

    $customerRelation = Mockery::mock(MorphOne::class);
    $customerRelation->shouldReceive('create')
        ->once()
        ->with([])
        ->andReturn($customer);

    $user = new class extends User {
        public function customer(): MorphOne
        {
            return $this->customerRelation;
        }

        public $customerRelation;
    };
    $user->customerRelation = $customerRelation;

    $result = $user->createAsCustomer();

    expect($result)->toBe($customer);
});

<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Checkout;
use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;

beforeEach(function () {
    Relation::morphMap([
        'users' => User::class,
    ]);

    Config::set('polar.access_token', 'test-token');
    Config::set('polar.server', 'sandbox');
});

afterEach(function () {
    Mockery::close();
});

it('can generate a checkout for a billable', function () {
    $user = User::factory()->create();
    $checkout = $user->checkout(['product_123']);

    expect($checkout)->toBeInstanceOf(Checkout::class);

    $reflection = new \ReflectionClass($checkout);
    $productsProperty = $reflection->getProperty('products');
    $productsProperty->setAccessible(true);

    expect($productsProperty->getValue($checkout))->toBe(['product_123']);
});

it('can generate a checkout for a billable with metadata', function () {
    $user = User::factory()->create();
    $checkout = $user->checkout(['product_123'], [], [], ['batch_id' => '789']);

    expect($checkout)->toBeInstanceOf(Checkout::class);

    $reflection = new \ReflectionClass($checkout);
    $metadataProperty = $reflection->getProperty('metadata');
    $metadataProperty->setAccessible(true);

    expect($metadataProperty->getValue($checkout))->toBe(['batch_id' => '789']);
});

it('automatically adds billable_id and billable_type to customer metadata', function () {
    $user = User::factory()->create();
    $checkout = $user->checkout(['product_123']);

    expect($checkout)->toBeInstanceOf(Checkout::class);

    $reflection = new \ReflectionClass($checkout);
    $customerMetadataProperty = $reflection->getProperty('customerMetadata');
    $customerMetadataProperty->setAccessible(true);

    $customerMetadata = $customerMetadataProperty->getValue($checkout);
    expect($customerMetadata)->toHaveKey('billable_id');
    expect($customerMetadata)->toHaveKey('billable_type');
    expect($customerMetadata['billable_id'])->toBe((string) $user->getKey());
    expect($customerMetadata['billable_type'])->toBe($user->getMorphClass());
});

it('throws exception when generating customer portal link without customer', function () {
    $user = User::factory()->create();

    expect(fn() => $user->customerPortalUrl())
        ->toThrow(\Danestves\LaravelPolar\Exceptions\InvalidCustomer::class);
});

it('throws exception when generating customer portal link without polar_id', function () {
    $user = User::factory()->create();
    Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => null,
    ]);

    expect(fn() => $user->customerPortalUrl())
        ->toThrow(\Danestves\LaravelPolar\Exceptions\InvalidCustomer::class);
});

it('can determine the generic trial on a billable', function () {
    $user = User::factory()->create();
    $customer = $user->createAsCustomer();

    expect($customer)->toBeInstanceOf(Customer::class);
    expect($customer->billable_id)->toBe($user->getKey());
    expect($customer->billable_type)->toBe($user->getMorphClass());
});

it('can check if billable is subscribed', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
    ]);

    $subscription = \Danestves\LaravelPolar\Subscription::factory()->active()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'product_id' => 'product_123',
    ]);

    $user->refresh();

    expect($user->subscribed())->toBeTrue();
    expect($user->subscribed('default'))->toBeTrue();
    expect($user->subscribed('default', 'product_123'))->toBeTrue();
    expect($user->subscribed('default', 'product_456'))->toBeFalse();
    expect($user->subscribedToProduct('product_123'))->toBeTrue();
    expect($user->subscribedToProduct('product_456'))->toBeFalse();
});

it('can check if billable has purchased a product', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
    ]);

    \Danestves\LaravelPolar\Order::factory()->paid()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'product_id' => 'product_123',
    ]);

    expect($user->hasPurchasedProduct('product_123'))->toBeTrue();
    expect($user->hasPurchasedProduct('product_456'))->toBeFalse();
});

it('can use charge method to create checkout with custom amount', function () {
    $user = User::factory()->create();
    $checkout = $user->charge(5000, ['product_123']);

    expect($checkout)->toBeInstanceOf(Checkout::class);

    $reflection = new \ReflectionClass($checkout);
    $amountProperty = $reflection->getProperty('amount');
    $amountProperty->setAccessible(true);

    expect($amountProperty->getValue($checkout))->toBe(5000);
});

it('can use subscribe method to create subscription checkout', function () {
    $user = User::factory()->create();
    $checkout = $user->subscribe('product_123', 'premium');

    expect($checkout)->toBeInstanceOf(Checkout::class);

    $reflection = new \ReflectionClass($checkout);
    $customerMetadataProperty = $reflection->getProperty('customerMetadata');
    $customerMetadataProperty->setAccessible(true);

    $customerMetadata = $customerMetadataProperty->getValue($checkout);
    expect($customerMetadata)->toHaveKey('subscription_type');
    expect($customerMetadata['subscription_type'])->toBe('premium');
});

it('can handle null parameters in checkout method', function () {
    $user = User::factory()->create();
    $checkout = $user->checkout(['product_123'], null, null, null);

    expect($checkout)->toBeInstanceOf(Checkout::class);

    $reflection = new \ReflectionClass($checkout);
    $customerMetadataProperty = $reflection->getProperty('customerMetadata');
    $customerMetadataProperty->setAccessible(true);

    $customerMetadata = $customerMetadataProperty->getValue($checkout);
    expect($customerMetadata)->toHaveKey('billable_id');
    expect($customerMetadata)->toHaveKey('billable_type');
});

it('can handle null parameters in subscribe method', function () {
    $user = User::factory()->create();
    $checkout = $user->subscribe('product_123', 'premium', null, null, null);

    expect($checkout)->toBeInstanceOf(Checkout::class);

    $reflection = new \ReflectionClass($checkout);
    $customerMetadataProperty = $reflection->getProperty('customerMetadata');
    $customerMetadataProperty->setAccessible(true);

    $customerMetadata = $customerMetadataProperty->getValue($checkout);
    expect($customerMetadata)->toHaveKey('subscription_type');
    expect($customerMetadata['subscription_type'])->toBe('premium');
});

it('can handle null parameters in charge method', function () {
    $user = User::factory()->create();
    $checkout = $user->charge(5000, ['product_123'], null, null, null);

    expect($checkout)->toBeInstanceOf(Checkout::class);

    $reflection = new \ReflectionClass($checkout);
    $amountProperty = $reflection->getProperty('amount');
    $amountProperty->setAccessible(true);

    expect($amountProperty->getValue($checkout))->toBe(5000);
});

it('links an existing Polar customer to the checkout', function () {
    fakePolar('v1/checkouts/', polarFixture('Checkout', ['url' => 'https://polar.sh/checkout/test']), 201);

    $user = User::factory()->create();
    Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'cus_existing',
    ]);

    $user->refresh()->checkout(['product_123'])->url();

    Http::assertSent(fn($request) => $request['customer_id'] === 'cus_existing'
        && ! array_key_exists('external_customer_id', $request->data()));
});

it('uses the stored external id for a linked customer that has one', function () {
    fakePolar('v1/checkouts/', polarFixture('Checkout', ['url' => 'https://polar.sh/checkout/test']), 201);

    $user = User::factory()->create();
    Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'cus_existing',
        'external_id' => 'users:1',
    ]);

    $user->refresh()->checkout(['product_123'])->url();

    Http::assertSent(fn($request) => $request['external_customer_id'] === 'users:1'
        && ! array_key_exists('customer_id', $request->data()));
});

it('gives Polar a stable external id when no customer exists yet', function () {
    fakePolar('v1/checkouts/', polarFixture('Checkout', ['url' => 'https://polar.sh/checkout/test']), 201);

    $user = User::factory()->create();

    $user->checkout(['product_123'])->url();

    Http::assertSent(fn($request) => $request['external_customer_id'] === $user->getMorphClass() . ':' . $user->getKey()
        && ! array_key_exists('customer_id', $request->data()));
});

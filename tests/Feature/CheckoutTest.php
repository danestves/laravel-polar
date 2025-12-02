<?php

use Danestves\LaravelPolar\Checkout;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('polar.access_token', 'test-token');
    Config::set('polar.server', 'sandbox');
});

afterEach(function () {
    Mockery::close();
});

it('can initiate a new checkout', function () {
    $checkout = Checkout::make(['product_123']);

    expect($checkout)->toBeInstanceOf(Checkout::class);

    $reflection = new \ReflectionClass($checkout);
    $productsProperty = $reflection->getProperty('products');
    $productsProperty->setAccessible(true);

    expect($productsProperty->getValue($checkout))->toBe(['product_123']);
});

it('can be redirected', function () {
    $checkout = Checkout::make(['product_123']);

    expect(fn() => $checkout->redirect())
        ->toThrow(\Polar\Models\Errors\APIException::class);
});

it('can set prefilled fields with dedicated methods', function () {
    $checkout = Checkout::make(['product_123'])
        ->withCustomerName('John Doe')
        ->withCustomerEmail('john@doe.com');

    $reflection = new \ReflectionClass($checkout);

    $nameProperty = $reflection->getProperty('customerName');
    $nameProperty->setAccessible(true);
    expect($nameProperty->getValue($checkout))->toBe('John Doe');

    $emailProperty = $reflection->getProperty('customerEmail');
    $emailProperty->setAccessible(true);
    expect($emailProperty->getValue($checkout))->toBe('john@doe.com');
});

it('can include metadata', function () {
    $checkout = Checkout::make(['product_123'])
        ->withMetadata(['batch_id' => '789']);

    $reflection = new \ReflectionClass($checkout);
    $metadataProperty = $reflection->getProperty('metadata');
    $metadataProperty->setAccessible(true);

    expect($metadataProperty->getValue($checkout))->toBe(['batch_id' => '789']);
});

it('can include prefilled fields and metadata', function () {
    $checkout = Checkout::make(['product_123'])
        ->withCustomerName('John Doe')
        ->withMetadata(['batch_id' => '789']);

    $reflection = new \ReflectionClass($checkout);

    $nameProperty = $reflection->getProperty('customerName');
    $nameProperty->setAccessible(true);
    expect($nameProperty->getValue($checkout))->toBe('John Doe');

    $metadataProperty = $reflection->getProperty('metadata');
    $metadataProperty->setAccessible(true);
    expect($metadataProperty->getValue($checkout))->toBe(['batch_id' => '789']);
});

it('can generate checkout URL', function () {
    $checkout = Checkout::make(['product_123']);

    expect(fn() => $checkout->url())
        ->toThrow(\Polar\Models\Errors\APIException::class);
});

it('implements Responsable contract', function () {
    $checkout = Checkout::make(['product_123']);

    expect(fn() => $checkout->toResponse(request()))
        ->toThrow(\Polar\Models\Errors\APIException::class);
});

it('can set all checkout options', function () {
    $checkout = Checkout::make(['product_123'])
        ->withCustomerName('John Doe')
        ->withCustomerEmail('john@doe.com')
        ->withCustomerTaxId('TAX123')
        ->withDiscountId('discount_123')
        ->withAmount(5000)
        ->withMetadata(['key' => 'value'])
        ->withCustomFieldData(['field1' => 'data1'])
        ->withSuccessUrl('https://example.com/success')
        ->withEmbedOrigin('https://example.com')
        ->withoutDiscountCodes();

    $reflection = new \ReflectionClass($checkout);

    expect($reflection->getProperty('customerName')->getValue($checkout))->toBe('John Doe');
    expect($reflection->getProperty('customerEmail')->getValue($checkout))->toBe('john@doe.com');
    expect($reflection->getProperty('customerTaxId')->getValue($checkout))->toBe('TAX123');
    expect($reflection->getProperty('discountId')->getValue($checkout))->toBe('discount_123');
    expect($reflection->getProperty('amount')->getValue($checkout))->toBe(5000);
    expect($reflection->getProperty('metadata')->getValue($checkout))->toBe(['key' => 'value']);
    expect($reflection->getProperty('customFieldData')->getValue($checkout))->toBe(['field1' => 'data1']);
    expect($reflection->getProperty('successUrl')->getValue($checkout))->toBe('https://example.com/success');
    expect($reflection->getProperty('embedOrigin')->getValue($checkout))->toBe('https://example.com');
    expect($reflection->getProperty('allowDiscountCodes')->getValue($checkout))->toBeFalse();
});

<?php

use Danestves\LaravelPolar\Enums\OrderStatus;
use Danestves\LaravelPolar\Order as PolarOrder;

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

class Order extends PolarOrder
{
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';
}

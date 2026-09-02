<?php

use Danestves\LaravelPolar\Data;

/**
 * Polar returns prices as `LegacyRecurringProductPrice|ProductPrice`, and both halves of that
 * union discriminate on `amount_type`. laravel-data only ever asks the first class in a union
 * to morph, so every shape has to resolve through `LegacyRecurringProductPrice::morph()`.
 */
dataset('product prices', [
    'fixed' => ['ProductPriceFixed', Data\ProductPriceFixed::class],
    'custom' => ['ProductPriceCustom', Data\ProductPriceCustom::class],
    'seat based' => ['ProductPriceSeatBased', Data\ProductPriceSeatBased::class],
    'metered unit' => ['ProductPriceMeteredUnit', Data\ProductPriceMeteredUnit::class],
    'legacy fixed' => ['LegacyRecurringProductPriceFixed', Data\LegacyRecurringProductPriceFixed::class],
    'legacy custom' => ['LegacyRecurringProductPriceCustom', Data\LegacyRecurringProductPriceCustom::class],
]);

it('hydrates every price shape in a product price list', function (string $schema, string $expected) {
    $product = Data\Product::from(polarFixture('Product', [
        'prices' => [polarFixture($schema)],
    ]));

    expect($product->prices[0])->toBeInstanceOf($expected);
})->with('product prices');

it('hydrates every price shape on a checkout', function (string $schema, string $expected) {
    $checkout = Data\Checkout::from(polarFixture('Checkout', [
        'product_price' => polarFixture($schema),
    ]));

    expect($checkout->productPrice)->toBeInstanceOf($expected);
})->with('product prices');

it('keeps the recurring interval of a legacy price', function () {
    $product = Data\Product::from(polarFixture('Product', [
        'prices' => [polarFixture('LegacyRecurringProductPriceFixed', ['recurring_interval' => 'month'])],
    ]));

    expect($product->prices[0])
        ->toBeInstanceOf(Data\LegacyRecurringProductPriceFixed::class)
        ->recurringInterval->toBe(Danestves\LaravelPolar\Enums\RecurringInterval::Month)
        ->type->toBe('recurring');
});

it('reads the seat tiers of a seat-based price', function () {
    $product = Data\Product::from(polarFixture('Product', [
        'prices' => [polarFixture('ProductPriceSeatBased', [
            'seat_tiers' => [
                'tiers' => [['min_seats' => 1, 'max_seats' => 10, 'price_per_seat' => 500]],
                'minimum_seats' => 1,
                'maximum_seats' => 10,
            ],
        ])],
    ]));

    expect($product->prices[0])->toBeInstanceOf(Data\ProductPriceSeatBased::class)
        ->and($product->prices[0]->seatTiers->tiers[0]->pricePerSeat)->toBe(500);
});

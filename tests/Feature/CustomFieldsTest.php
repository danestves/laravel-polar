<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Order;
use Illuminate\Support\Facades\Http;

it('creates a custom field', function () {
    fakePolar('v1/custom-fields/', polarFixture('CustomFieldText', [
        'id' => 'cf_1',
        'slug' => 'company',
    ]), 201);

    $field = LaravelPolar::createCustomField([
        'type' => 'text',
        'slug' => 'company',
        'name' => 'Company',
        'organization_id' => 'org_1',
    ]);

    expect($field)->toBeInstanceOf(Data\CustomFieldText::class)
        ->and($field->slug)->toBe('company');

    Http::assertSent(fn($request) => $request->method() === 'POST' && $request['slug'] === 'company');
});

it('updates a custom field', function () {
    fakePolar('v1/custom-fields/cf_1', polarFixture('CustomFieldText', [
        'id' => 'cf_1',
        'name' => 'Renamed',
    ]));

    expect(LaravelPolar::updateCustomField('cf_1', ['name' => 'Renamed'])->name)->toBe('Renamed');

    Http::assertSent(fn($request) => $request->method() === 'PATCH');
});

it('deletes a custom field', function () {
    fakePolar('v1/custom-fields/cf_1', [], 204);

    LaravelPolar::deleteCustomField('cf_1');

    Http::assertSent(fn($request) => $request->method() === 'DELETE');
});

it('lists custom fields', function () {
    fakePolarList('v1/custom-fields/*', [polarFixture('CustomFieldText', ['id' => 'cf_1'])]);

    expect(LaravelPolar::listCustomFields()->first()->id)->toBe('cf_1');
});

it('gets a custom field by id', function () {
    fakePolar('v1/custom-fields/cf_1', polarFixture('CustomFieldText', ['id' => 'cf_1']));

    expect(LaravelPolar::getCustomField('cf_1'))->toBeInstanceOf(Data\CustomFieldText::class);
});

it('reads an order\'s custom field data from Polar and memoizes it', function () {
    fakePolar('v1/orders/order_1', polarFixture('Order', [
        'id' => 'order_1',
        'custom_field_data' => ['company' => 'Acme'],
    ]));

    $order = Order::factory()->paid()->create(['polar_id' => 'order_1']);

    expect($order->customFieldData())->toBe(['company' => 'Acme'])
        ->and($order->customFieldData())->toBe(['company' => 'Acme']);

    // Memoized: the second read must not hit the API again.
    Http::assertSentCount(1);
});

it('returns no custom field data for an order that was never synced', function () {
    $order = Order::factory()->paid()->create(['polar_id' => null]);

    expect($order->customFieldData())->toBe([]);

    Http::assertNothingSent();
});

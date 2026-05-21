<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Order;
use Illuminate\Support\Facades\Config;
use Mockery;
use Polar\Models\Components;
use Polar\Models\Errors;
use Polar\Models\Operations;

beforeEach(function () {
    Config::set('polar.access_token', 'test-token');
    Config::set('polar.server', 'sandbox');
});

afterEach(function () {
    resetLaravelPolarSdk();
    Mockery::close();
});

function createMockedSdkWithCustomFields(): array
{
    $base = createBaseMockedSdk();
    $sdk = $base['sdk'];

    $customFields = Mockery::mock(\Polar\CustomFields::class);
    $reflectionSdk = new \ReflectionClass($sdk);
    $customFieldsProperty = $reflectionSdk->getProperty('customFields');
    $customFieldsProperty->setAccessible(true);
    $customFieldsProperty->setValue($sdk, $customFields);

    return ['sdk' => $sdk, 'customFields' => $customFields];
}

function createMockedSdkWithOrders(): array
{
    $base = createBaseMockedSdk();
    $sdk = $base['sdk'];

    $orders = Mockery::mock(\Polar\Orders::class);
    $reflectionSdk = new \ReflectionClass($sdk);
    $ordersProperty = $reflectionSdk->getProperty('orders');
    $ordersProperty->setAccessible(true);
    $ordersProperty->setValue($sdk, $orders);

    return ['sdk' => $sdk, 'orders' => $orders];
}

it('createCustomField forwards to SDK and returns the custom field on 201', function () {
    $mocked = createMockedSdkWithCustomFields();
    setLaravelPolarSdk($mocked['sdk']);

    $cfMock = Mockery::mock(Components\CustomFieldText::class);
    $response = new Operations\CustomFieldsCreateResponse(
        contentType: 'application/json',
        statusCode: 201,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        customField: $cfMock,
    );

    $mocked['customFields']->shouldReceive('create')->once()->andReturn($response);

    $request = new Components\CustomFieldCreateText(
        slug: 'company',
        name: 'Company name',
        properties: new Components\CustomFieldTextProperties(),
    );

    expect(LaravelPolar::createCustomField($request))->toBe($cfMock);
});

it('updateCustomField forwards to SDK and returns the updated custom field on 200', function () {
    $mocked = createMockedSdkWithCustomFields();
    setLaravelPolarSdk($mocked['sdk']);

    $cfMock = Mockery::mock(Components\CustomFieldText::class);
    $response = new Operations\CustomFieldsUpdateResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        customField: $cfMock,
    );

    $mocked['customFields']->shouldReceive('update')
        ->once()
        ->withArgs(fn($body, string $id) => $id === 'cf_xyz' && $body instanceof Components\CustomFieldUpdateText)
        ->andReturn($response);

    $request = new Components\CustomFieldUpdateText(name: 'Updated label');

    expect(LaravelPolar::updateCustomField('cf_xyz', $request))->toBe($cfMock);
});

it('deleteCustomField accepts 200 or 204 and throws otherwise', function () {
    $mocked = createMockedSdkWithCustomFields();
    setLaravelPolarSdk($mocked['sdk']);

    $ok = new Operations\CustomFieldsDeleteResponse(
        contentType: 'application/json',
        statusCode: 204,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
    );

    $mocked['customFields']->shouldReceive('delete')->once()->andReturn($ok);
    LaravelPolar::deleteCustomField('cf_ok');

    $err = new Operations\CustomFieldsDeleteResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
    );

    $mocked['customFields']->shouldReceive('delete')->once()->andReturn($err);
    expect(fn() => LaravelPolar::deleteCustomField('cf_bad'))
        ->toThrow(Errors\APIException::class);
});

it('listCustomFields returns the first 200 page from the generator', function () {
    $mocked = createMockedSdkWithCustomFields();
    setLaravelPolarSdk($mocked['sdk']);

    $list = new Components\ListResourceCustomField(
        items: [Mockery::mock(Components\CustomFieldText::class)],
        pagination: new Components\Pagination(totalCount: 1, maxPage: 1),
    );

    $response = new Operations\CustomFieldsListResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        listResourceCustomField: $list,
    );

    $mocked['customFields']->shouldReceive('list')->andReturn((function () use ($response) {
        yield $response;
    })());

    $result = LaravelPolar::listCustomFields();

    expect($result)->toBe($response);
    expect($result->listResourceCustomField?->items)->toHaveCount(1);
});

it('getCustomField returns the custom field on 200', function () {
    $mocked = createMockedSdkWithCustomFields();
    setLaravelPolarSdk($mocked['sdk']);

    $cfMock = Mockery::mock(Components\CustomFieldText::class);
    $response = new Operations\CustomFieldsGetResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        customField: $cfMock,
    );

    $mocked['customFields']->shouldReceive('get')->once()->with('cf_abc')->andReturn($response);

    expect(LaravelPolar::getCustomField('cf_abc'))->toBe($cfMock);
});

it('getCustomField throws when SDK returns non-200', function () {
    $mocked = createMockedSdkWithCustomFields();
    setLaravelPolarSdk($mocked['sdk']);

    $response = new Operations\CustomFieldsGetResponse(
        contentType: 'application/json',
        statusCode: 404,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        customField: null,
    );

    $mocked['customFields']->shouldReceive('get')->andReturn($response);

    expect(fn() => LaravelPolar::getCustomField('cf_missing'))
        ->toThrow(Errors\APIException::class);
});

it('Order::customFieldData fetches from Polar and memoizes', function () {
    $mocked = createMockedSdkWithOrders();
    setLaravelPolarSdk($mocked['sdk']);

    $order = Order::factory()->paid()->create(['polar_id' => 'ord_with_cf']);

    $sdkOrder = Mockery::mock(Components\Order::class);
    $sdkOrder->customFieldData = ['company' => 'Acme', 'volume' => 42];

    $response = new Operations\OrdersGetResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        order: $sdkOrder,
    );

    $mocked['orders']->shouldReceive('get')
        ->once() // Memoization: only called once even if accessed twice
        ->withArgs(fn(string $id) => $id === 'ord_with_cf')
        ->andReturn($response);

    $first = $order->customFieldData();
    $second = $order->customFieldData();

    expect($first)->toBe(['company' => 'Acme', 'volume' => 42]);
    expect($second)->toBe($first);
});

it('Order::customFieldData returns an empty array when the order has no polar_id', function () {
    $order = Order::factory()->paid()->create(['polar_id' => null]);

    expect($order->customFieldData())->toBe([]);
});

it('Order::customFieldData returns an empty array when the SDK returns no order', function () {
    $mocked = createMockedSdkWithOrders();
    setLaravelPolarSdk($mocked['sdk']);

    $order = Order::factory()->paid()->create(['polar_id' => 'ord_phantom']);

    $response = new Operations\OrdersGetResponse(
        contentType: 'application/json',
        statusCode: 404,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        order: null,
    );

    $mocked['orders']->shouldReceive('get')->andReturn($response);

    expect($order->customFieldData())->toBe([]);
});

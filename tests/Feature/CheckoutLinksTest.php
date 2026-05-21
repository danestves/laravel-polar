<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\LaravelPolar;
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

function createMockedSdkWithCheckoutLinks(): array
{
    $base = createBaseMockedSdk();
    $sdk = $base['sdk'];

    $checkoutLinks = Mockery::mock(\Polar\CheckoutLinks::class);
    $reflectionSdk = new \ReflectionClass($sdk);
    $checkoutLinksProperty = $reflectionSdk->getProperty('checkoutLinks');
    $checkoutLinksProperty->setAccessible(true);
    $checkoutLinksProperty->setValue($sdk, $checkoutLinks);

    return ['sdk' => $sdk, 'checkoutLinks' => $checkoutLinks];
}

it('createCheckoutLink forwards to SDK and returns link on 201', function () {
    $mocked = createMockedSdkWithCheckoutLinks();
    setLaravelPolarSdk($mocked['sdk']);

    $linkMock = Mockery::mock(Components\CheckoutLink::class);
    $response = new Operations\CheckoutLinksCreateResponse(
        contentType: 'application/json',
        statusCode: 201,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        checkoutLink: $linkMock,
    );

    $mocked['checkoutLinks']->shouldReceive('create')->once()->andReturn($response);

    $request = new Components\CheckoutLinkCreateProduct(
        productId: 'prod_xxx',
        paymentProcessor: 'stripe',
    );

    expect(LaravelPolar::createCheckoutLink($request))->toBe($linkMock);
});

it('createCheckoutLink throws on non-201', function () {
    $mocked = createMockedSdkWithCheckoutLinks();
    setLaravelPolarSdk($mocked['sdk']);

    $response = new Operations\CheckoutLinksCreateResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        checkoutLink: null,
    );

    $mocked['checkoutLinks']->shouldReceive('create')->andReturn($response);

    $request = new Components\CheckoutLinkCreateProduct(
        productId: 'prod_xxx',
        paymentProcessor: 'stripe',
    );

    expect(fn() => LaravelPolar::createCheckoutLink($request))
        ->toThrow(Errors\APIException::class);
});

it('updateCheckoutLink forwards to SDK and returns updated link on 200', function () {
    $mocked = createMockedSdkWithCheckoutLinks();
    setLaravelPolarSdk($mocked['sdk']);

    $linkMock = Mockery::mock(Components\CheckoutLink::class);
    $response = new Operations\CheckoutLinksUpdateResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        checkoutLink: $linkMock,
    );

    $mocked['checkoutLinks']->shouldReceive('update')
        ->once()
        ->withArgs(fn($body, string $id) => $id === 'cl_xyz' && $body instanceof Components\CheckoutLinkUpdate)
        ->andReturn($response);

    $request = new Components\CheckoutLinkUpdate(label: 'Updated label');

    expect(LaravelPolar::updateCheckoutLink('cl_xyz', $request))->toBe($linkMock);
});

it('deleteCheckoutLink accepts 200 or 204 and throws otherwise', function () {
    $mocked = createMockedSdkWithCheckoutLinks();
    setLaravelPolarSdk($mocked['sdk']);

    $ok = new Operations\CheckoutLinksDeleteResponse(
        contentType: 'application/json',
        statusCode: 204,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
    );

    $mocked['checkoutLinks']->shouldReceive('delete')->once()->andReturn($ok);
    LaravelPolar::deleteCheckoutLink('cl_ok');

    $err = new Operations\CheckoutLinksDeleteResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
    );

    $mocked['checkoutLinks']->shouldReceive('delete')->once()->andReturn($err);
    expect(fn() => LaravelPolar::deleteCheckoutLink('cl_bad'))
        ->toThrow(Errors\APIException::class);
});

it('listCheckoutLinks returns the first 200 page from the generator', function () {
    $mocked = createMockedSdkWithCheckoutLinks();
    setLaravelPolarSdk($mocked['sdk']);

    $list = new Components\ListResourceCheckoutLink(
        items: [Mockery::mock(Components\CheckoutLink::class)],
        pagination: new Components\Pagination(totalCount: 1, maxPage: 1),
    );

    $response = new Operations\CheckoutLinksListResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        listResourceCheckoutLink: $list,
    );

    $mocked['checkoutLinks']->shouldReceive('list')->andReturn((function () use ($response) {
        yield $response;
    })());

    $result = LaravelPolar::listCheckoutLinks();

    expect($result)->toBe($response);
    expect($result->listResourceCheckoutLink?->items)->toHaveCount(1);
});

it('getCheckoutLink returns the link on 200', function () {
    $mocked = createMockedSdkWithCheckoutLinks();
    setLaravelPolarSdk($mocked['sdk']);

    $linkMock = Mockery::mock(Components\CheckoutLink::class);
    $response = new Operations\CheckoutLinksGetResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        checkoutLink: $linkMock,
    );

    $mocked['checkoutLinks']->shouldReceive('get')
        ->once()
        ->with('cl_abc')
        ->andReturn($response);

    expect(LaravelPolar::getCheckoutLink('cl_abc'))->toBe($linkMock);
});

it('getCheckoutLink throws when SDK returns non-200', function () {
    $mocked = createMockedSdkWithCheckoutLinks();
    setLaravelPolarSdk($mocked['sdk']);

    $response = new Operations\CheckoutLinksGetResponse(
        contentType: 'application/json',
        statusCode: 404,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        checkoutLink: null,
    );

    $mocked['checkoutLinks']->shouldReceive('get')->andReturn($response);

    expect(fn() => LaravelPolar::getCheckoutLink('cl_missing'))
        ->toThrow(Errors\APIException::class);
});

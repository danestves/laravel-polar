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

function createMockedSdkWithDiscounts(): array
{
    $base = createBaseMockedSdk();
    $sdk = $base['sdk'];

    $discounts = Mockery::mock(\Polar\Discounts::class);
    $reflectionSdk = new \ReflectionClass($sdk);
    $discountsProperty = $reflectionSdk->getProperty('discounts');
    $discountsProperty->setAccessible(true);
    $discountsProperty->setValue($sdk, $discounts);

    return ['sdk' => $sdk, 'discounts' => $discounts];
}

it('createDiscount forwards to SDK and returns the discount on 201', function () {
    $mocked = createMockedSdkWithDiscounts();
    setLaravelPolarSdk($mocked['sdk']);

    $discountMock = Mockery::mock(Components\DiscountPercentageOnceForeverDuration::class);
    $response = new Operations\DiscountsCreateResponse(
        contentType: 'application/json',
        statusCode: 201,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        discount: $discountMock,
    );

    $mocked['discounts']->shouldReceive('create')->once()->andReturn($response);

    $request = new Components\DiscountPercentageOnceForeverDurationCreate(
        name: '50% Off',
        type: Components\DiscountType::Percentage,
        duration: Components\DiscountDuration::Once,
        basisPoints: 5000,
        organizationId: 'org_123',
    );

    expect(LaravelPolar::createDiscount($request))->toBe($discountMock);
});

it('createDiscount throws when SDK returns non-201', function () {
    $mocked = createMockedSdkWithDiscounts();
    setLaravelPolarSdk($mocked['sdk']);

    $response = new Operations\DiscountsCreateResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        discount: null,
    );

    $mocked['discounts']->shouldReceive('create')->andReturn($response);

    $request = new Components\DiscountPercentageOnceForeverDurationCreate(
        name: 'X',
        type: Components\DiscountType::Percentage,
        duration: Components\DiscountDuration::Once,
        basisPoints: 100,
        organizationId: 'org_123',
    );

    expect(fn() => LaravelPolar::createDiscount($request))
        ->toThrow(Errors\APIException::class);
});

it('updateDiscount forwards to SDK with the right id and returns updated discount on 200', function () {
    $mocked = createMockedSdkWithDiscounts();
    setLaravelPolarSdk($mocked['sdk']);

    $discountMock = Mockery::mock(Components\DiscountPercentageOnceForeverDuration::class);
    $response = new Operations\DiscountsUpdateResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        discount: $discountMock,
    );

    $mocked['discounts']->shouldReceive('update')
        ->once()
        ->withArgs(function ($body, string $id) {
            return $id === 'disc_xyz' && $body instanceof Components\DiscountUpdate;
        })
        ->andReturn($response);

    $request = new Components\DiscountUpdate(name: 'Updated name');

    expect(LaravelPolar::updateDiscount('disc_xyz', $request))->toBe($discountMock);
});

it('deleteDiscount accepts a 200 or 204 response and throws otherwise', function () {
    $mocked = createMockedSdkWithDiscounts();
    setLaravelPolarSdk($mocked['sdk']);

    $okResponse = new Operations\DiscountsDeleteResponse(
        contentType: 'application/json',
        statusCode: 204,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
    );

    $mocked['discounts']->shouldReceive('delete')->once()->andReturn($okResponse);

    LaravelPolar::deleteDiscount('disc_ok');

    $errResponse = new Operations\DiscountsDeleteResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
    );

    $mocked['discounts']->shouldReceive('delete')->once()->andReturn($errResponse);

    expect(fn() => LaravelPolar::deleteDiscount('disc_bad'))
        ->toThrow(Errors\APIException::class);
});

it('listDiscounts returns the first 200 page from the generator', function () {
    $mocked = createMockedSdkWithDiscounts();
    setLaravelPolarSdk($mocked['sdk']);

    $list = new Components\ListResourceDiscount(
        items: [Mockery::mock(Components\DiscountPercentageOnceForeverDuration::class)],
        pagination: new Components\Pagination(totalCount: 1, maxPage: 1),
    );

    $response = new Operations\DiscountsListResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        listResourceDiscount: $list,
    );

    $mocked['discounts']->shouldReceive('list')->andReturn((function () use ($response) {
        yield $response;
    })());

    $result = LaravelPolar::listDiscounts();

    expect($result)->toBe($response);
    expect($result->listResourceDiscount?->items)->toHaveCount(1);
});

it('getDiscount returns the discount on 200', function () {
    $mocked = createMockedSdkWithDiscounts();
    setLaravelPolarSdk($mocked['sdk']);

    $discountMock = Mockery::mock(Components\DiscountPercentageOnceForeverDuration::class);
    $response = new Operations\DiscountsGetResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        discount: $discountMock,
    );

    $mocked['discounts']->shouldReceive('get')
        ->once()
        ->with('disc_abc')
        ->andReturn($response);

    expect(LaravelPolar::getDiscount('disc_abc'))->toBe($discountMock);
});

it('getDiscount throws when the SDK returns non-200', function () {
    $mocked = createMockedSdkWithDiscounts();
    setLaravelPolarSdk($mocked['sdk']);

    $response = new Operations\DiscountsGetResponse(
        contentType: 'application/json',
        statusCode: 404,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        discount: null,
    );

    $mocked['discounts']->shouldReceive('get')->andReturn($response);

    expect(fn() => LaravelPolar::getDiscount('disc_missing'))
        ->toThrow(Errors\APIException::class);
});

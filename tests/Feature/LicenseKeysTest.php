<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Support\Facades\Config;
use Mockery;
use Polar\Models\Components;
use Polar\Models\Errors;
use Polar\Models\Operations;

beforeEach(function () {
    Config::set('polar.access_token', 'test-token');
    Config::set('polar.server', 'sandbox');
    Config::set('polar.organization_id', null);
});

afterEach(function () {
    resetLaravelPolarSdk();
    Mockery::close();
});

function createMockedSdkWithLicenseKeys(): array
{
    $base = createBaseMockedSdk();
    $sdk = $base['sdk'];

    $licenseKeys = Mockery::mock(\Polar\LicenseKeys::class);
    $reflectionSdk = new \ReflectionClass($sdk);
    $property = $reflectionSdk->getProperty('licenseKeys');
    $property->setAccessible(true);
    $property->setValue($sdk, $licenseKeys);

    return ['sdk' => $sdk, 'licenseKeys' => $licenseKeys];
}

it('listLicenseKeys returns the first 200 page from the generator', function () {
    $mocked = createMockedSdkWithLicenseKeys();
    setLaravelPolarSdk($mocked['sdk']);

    $list = new Components\ListResourceLicenseKeyRead(
        items: [Mockery::mock(Components\LicenseKeyRead::class)],
        pagination: new Components\Pagination(totalCount: 1, maxPage: 1),
    );

    $response = new Operations\LicenseKeysListResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        listResourceLicenseKeyRead: $list,
    );

    $mocked['licenseKeys']->shouldReceive('list')->andReturn((function () use ($response) {
        yield $response;
    })());

    $result = LaravelPolar::listLicenseKeys();

    expect($result)->toBe($response);
    expect($result->listResourceLicenseKeyRead?->items)->toHaveCount(1);
});

it('getLicenseKey returns the LicenseKeyWithActivations on 200', function () {
    $mocked = createMockedSdkWithLicenseKeys();
    setLaravelPolarSdk($mocked['sdk']);

    $keyMock = Mockery::mock(Components\LicenseKeyWithActivations::class);
    $response = new Operations\LicenseKeysGetResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        licenseKeyWithActivations: $keyMock,
    );

    $mocked['licenseKeys']->shouldReceive('get')->once()->andReturn($response);

    expect(LaravelPolar::getLicenseKey('lk_abc'))->toBe($keyMock);
});

it('updateLicenseKey returns the updated LicenseKeyRead on 200', function () {
    $mocked = createMockedSdkWithLicenseKeys();
    setLaravelPolarSdk($mocked['sdk']);

    $keyMock = Mockery::mock(Components\LicenseKeyRead::class);
    $response = new Operations\LicenseKeysUpdateResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        licenseKeyRead: $keyMock,
    );

    $mocked['licenseKeys']->shouldReceive('update')
        ->once()
        ->withArgs(fn($body, string $id) => $id === 'lk_abc' && $body instanceof Components\LicenseKeyUpdate)
        ->andReturn($response);

    expect(LaravelPolar::updateLicenseKey('lk_abc', new Components\LicenseKeyUpdate(limitActivations: 5)))->toBe($keyMock);
});

it('validateLicenseKey forwards organizationId from the explicit argument', function () {
    $mocked = createMockedSdkWithLicenseKeys();
    setLaravelPolarSdk($mocked['sdk']);

    $vMock = Mockery::mock(Components\ValidatedLicenseKey::class);
    $response = new Operations\LicenseKeysValidateResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        validatedLicenseKey: $vMock,
    );

    $mocked['licenseKeys']->shouldReceive('validate')
        ->once()
        ->withArgs(function (Components\LicenseKeyValidate $body) {
            return $body->key === 'lic_xxx' && $body->organizationId === 'org_passed';
        })
        ->andReturn($response);

    expect(LaravelPolar::validateLicenseKey('lic_xxx', 'org_passed'))->toBe($vMock);
});

it('validateLicenseKey falls back to polar.organization_id when not passed', function () {
    Config::set('polar.organization_id', 'org_from_config');

    $mocked = createMockedSdkWithLicenseKeys();
    setLaravelPolarSdk($mocked['sdk']);

    $vMock = Mockery::mock(Components\ValidatedLicenseKey::class);
    $response = new Operations\LicenseKeysValidateResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        validatedLicenseKey: $vMock,
    );

    $mocked['licenseKeys']->shouldReceive('validate')
        ->once()
        ->withArgs(fn(Components\LicenseKeyValidate $body) => $body->organizationId === 'org_from_config')
        ->andReturn($response);

    LaravelPolar::validateLicenseKey('lic_xxx');
});

it('validateLicenseKey throws InvalidArgumentException when no org id is available', function () {
    $mocked = createMockedSdkWithLicenseKeys();
    setLaravelPolarSdk($mocked['sdk']);

    expect(fn() => LaravelPolar::validateLicenseKey('lic_xxx'))
        ->toThrow(\InvalidArgumentException::class, 'Polar organization id is required');
});

it('activateLicenseKey forwards label and metadata to the SDK', function () {
    Config::set('polar.organization_id', 'org_from_config');

    $mocked = createMockedSdkWithLicenseKeys();
    setLaravelPolarSdk($mocked['sdk']);

    $aMock = Mockery::mock(Components\LicenseKeyActivationRead::class);
    $response = new Operations\LicenseKeysActivateResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        licenseKeyActivationRead: $aMock,
    );

    $mocked['licenseKeys']->shouldReceive('activate')
        ->once()
        ->withArgs(function (Components\LicenseKeyActivate $body) {
            return $body->key === 'lic_xxx'
                && $body->organizationId === 'org_from_config'
                && $body->label === 'My MacBook'
                && $body->meta === ['hostname' => 'macbook-pro'];
        })
        ->andReturn($response);

    LaravelPolar::activateLicenseKey('lic_xxx', 'My MacBook', meta: ['hostname' => 'macbook-pro']);
});

it('deactivateLicenseKey accepts 200 and 204 from the SDK', function () {
    Config::set('polar.organization_id', 'org_from_config');

    $mocked = createMockedSdkWithLicenseKeys();
    setLaravelPolarSdk($mocked['sdk']);

    $ok = new Operations\LicenseKeysDeactivateResponse(
        contentType: 'application/json',
        statusCode: 204,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
    );

    $mocked['licenseKeys']->shouldReceive('deactivate')->once()->andReturn($ok);
    LaravelPolar::deactivateLicenseKey('lic_xxx', 'act_xxx');

    $err = new Operations\LicenseKeysDeactivateResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
    );
    $mocked['licenseKeys']->shouldReceive('deactivate')->once()->andReturn($err);
    expect(fn() => LaravelPolar::deactivateLicenseKey('lic_xxx', 'act_xxx'))
        ->toThrow(Errors\APIException::class);
});

it('$user->licenseKeys() throws when the customer has not been created yet', function () {
    $user = User::factory()->create();

    expect(fn() => $user->licenseKeys())->toThrow(InvalidCustomer::class);
});

it('$user->licenseKeys() returns an empty Collection when no customer exists', function () {
    $user = User::factory()->create();

    expect(fn() => $user->licenseKeys())->toThrow(InvalidCustomer::class);
});

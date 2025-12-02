<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Support\Facades\Config;
use Mockery;
use Polar\Models\Components;
use Polar\Models\Errors;
use Polar\Models\Operations;
use Psr\Http\Message\ResponseInterface;

beforeEach(function () {
    Config::set('polar.access_token', 'test-token');
    Config::set('polar.server', 'sandbox');
});

afterEach(function () {
    // Reset SDK instance after each test
    $reflection = new \ReflectionClass(LaravelPolar::class);
    $sdkProperty = $reflection->getProperty('sdkInstance');
    $sdkProperty->setAccessible(true);
    $sdkProperty->setValue(null, null);

    Mockery::close();
});

function createMockedSdkWithBenefits(): array
{
    $sdkConfig = Mockery::mock(\Polar\SDKConfiguration::class);
    $sdkConfig->shouldReceive('getTemplatedServerUrl')->andReturn('https://sandbox-api.polar.sh');
    $hooks = Mockery::mock(\Polar\Hooks\SDKHooks::class);
    $mockClient = Mockery::mock(\GuzzleHttp\ClientInterface::class);
    $sdkRequestContext = new \Polar\Hooks\SDKRequestContext('https://sandbox-api.polar.sh', $mockClient);
    $hooks->shouldReceive('sdkInit')->andReturn($sdkRequestContext);
    $reflectionConfig = new \ReflectionClass($sdkConfig);
    $hooksProperty = $reflectionConfig->getProperty('hooks');
    $hooksProperty->setAccessible(true);
    $hooksProperty->setValue($sdkConfig, $hooks);
    $clientProperty = $reflectionConfig->getProperty('client');
    $clientProperty->setAccessible(true);
    $clientProperty->setValue($sdkConfig, $mockClient);
    $sdk = new \Polar\Polar($sdkConfig);
    $benefits = Mockery::mock(\Polar\Benefits::class);
    $reflectionSdk = new \ReflectionClass($sdk);
    $benefitsProperty = $reflectionSdk->getProperty('benefits');
    $benefitsProperty->setAccessible(true);
    $benefitsProperty->setValue($sdk, $benefits);

    return ['sdk' => $sdk, 'benefits' => $benefits];
}

function setLaravelPolarSdk(\Polar\Polar $sdk): void
{
    $reflection = new \ReflectionClass(LaravelPolar::class);
    $sdkProperty = $reflection->getProperty('sdkInstance');
    $sdkProperty->setAccessible(true);
    $sdkProperty->setValue(null, $sdk);
}

it('can list benefits for an organization', function () {
    $user = User::factory()->create();
    $organizationId = 'org_123';

    $mocked = createMockedSdkWithBenefits();
    $sdk = $mocked['sdk'];
    $benefits = $mocked['benefits'];
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $mockListResource = Mockery::mock(Components\ListResourceBenefit::class);
    $response = new Operations\BenefitsListResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: $mockRawResponse,
        listResourceBenefit: $mockListResource,
    );
    $generator = function () use ($response) {
        yield $response;
    };
    $benefits->shouldReceive('list')
        ->once()
        ->andReturn($generator());

    setLaravelPolarSdk($sdk);

    $result = $user->listBenefits($organizationId);

    expect($result)->toBeInstanceOf(Operations\BenefitsListResponse::class);
});

it('can get a specific benefit by id', function () {
    $user = User::factory()->create();
    $benefitId = 'benefit_123';

    $mocked = createMockedSdkWithBenefits();
    $sdk = $mocked['sdk'];
    $benefits = $mocked['benefits'];
    $mockBenefit = Mockery::mock(Components\BenefitCustom::class);
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\BenefitsGetResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: $mockRawResponse,
        benefit: $mockBenefit,
    );
    $benefits->shouldReceive('get')
        ->once()
        ->with(Mockery::on(fn($arg) => $arg === $benefitId))
        ->andReturn($response);

    setLaravelPolarSdk($sdk);

    $result = $user->getBenefit($benefitId);

    expect($result)->toBe($mockBenefit);
});

it('can list benefit grants for a benefit', function () {
    $user = User::factory()->create();
    $benefitId = 'benefit_123';

    $mocked = createMockedSdkWithBenefits();
    $sdk = $mocked['sdk'];
    $benefits = $mocked['benefits'];
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $mockListResource = Mockery::mock(Components\ListResourceBenefitGrant::class);
    $response = new Operations\BenefitsGrantsResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: $mockRawResponse,
        listResourceBenefitGrant: $mockListResource,
    );
    $generator = function () use ($response) {
        yield $response;
    };
    $benefits->shouldReceive('grants')
        ->once()
        ->andReturn($generator());

    setLaravelPolarSdk($sdk);

    $result = $user->listBenefitGrants($benefitId);

    expect($result)->toBeInstanceOf(Operations\BenefitsGrantsResponse::class);
});

it('can create a benefit via LaravelPolar facade', function () {
    $benefitRequest = new Components\BenefitCustomCreate(
        description: 'Test Benefit',
        organizationId: 'org_123',
        properties: new Components\BenefitCustomCreateProperties(),
    );

    $mocked = createMockedSdkWithBenefits();
    $sdk = $mocked['sdk'];
    $benefits = $mocked['benefits'];
    $mockBenefit = Mockery::mock(Components\BenefitCustom::class);
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\BenefitsCreateResponse(
        contentType: 'application/json',
        statusCode: 201,
        rawResponse: $mockRawResponse,
        benefit: $mockBenefit,
    );
    $benefits->shouldReceive('create')
        ->once()
        ->andReturn($response);

    setLaravelPolarSdk($sdk);

    $result = LaravelPolar::createBenefit($benefitRequest);

    expect($result)->toBe($mockBenefit);
});

it('can update a benefit via LaravelPolar facade', function () {
    $benefitId = 'benefit_123';
    $benefitRequest = new Components\BenefitCustomUpdate(
        description: 'Updated Benefit',
        properties: new Components\BenefitCustomProperties(),
    );

    $mocked = createMockedSdkWithBenefits();
    $sdk = $mocked['sdk'];
    $benefits = $mocked['benefits'];
    $mockBenefit = Mockery::mock(Components\BenefitCustom::class);
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\BenefitsUpdateResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: $mockRawResponse,
        benefit: $mockBenefit,
    );
    $benefits->shouldReceive('update')
        ->once()
        ->andReturn($response);

    setLaravelPolarSdk($sdk);

    $result = LaravelPolar::updateBenefit($benefitId, $benefitRequest);

    expect($result)->toBe($mockBenefit);
});

it('can delete a benefit via LaravelPolar facade', function () {
    $benefitId = 'benefit_123';

    $mocked = createMockedSdkWithBenefits();
    $sdk = $mocked['sdk'];
    $benefits = $mocked['benefits'];
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\BenefitsDeleteResponse(
        contentType: 'application/json',
        statusCode: 204,
        rawResponse: $mockRawResponse,
    );
    $benefits->shouldReceive('delete')
        ->once()
        ->with(Mockery::on(fn($arg) => $arg === $benefitId))
        ->andReturn($response);

    setLaravelPolarSdk($sdk);

    LaravelPolar::deleteBenefit($benefitId);

    expect(true)->toBeTrue();
});

it('throws exception when creating benefit fails', function () {
    $benefitRequest = new Components\BenefitCustomCreate(
        description: 'Test Benefit',
        organizationId: 'org_123',
        properties: new Components\BenefitCustomCreateProperties(),
    );

    $mocked = createMockedSdkWithBenefits();
    $sdk = $mocked['sdk'];
    $benefits = $mocked['benefits'];
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\BenefitsCreateResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: $mockRawResponse,
        benefit: null,
    );
    $benefits->shouldReceive('create')
        ->once()
        ->andReturn($response);

    setLaravelPolarSdk($sdk);

    expect(fn() => LaravelPolar::createBenefit($benefitRequest))
        ->toThrow(Errors\APIException::class);
});

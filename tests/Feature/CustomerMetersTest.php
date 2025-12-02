<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Customer;
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

function createMockedSdkWithEvents(): array
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
    $events = Mockery::mock(\Polar\Events::class);
    $reflectionSdk = new \ReflectionClass($sdk);
    $eventsProperty = $reflectionSdk->getProperty('events');
    $eventsProperty->setAccessible(true);
    $eventsProperty->setValue($sdk, $events);

    return ['sdk' => $sdk, 'events' => $events];
}

function createMockedSdkWithCustomerMeters(): array
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
    $customerMeters = Mockery::mock(\Polar\CustomerMeters::class);
    $reflectionSdk = new \ReflectionClass($sdk);
    $customerMetersProperty = $reflectionSdk->getProperty('customerMeters');
    $customerMetersProperty->setAccessible(true);
    $customerMetersProperty->setValue($sdk, $customerMeters);

    return ['sdk' => $sdk, 'customerMeters' => $customerMeters];
}

it('can ingest a single usage event', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'customer_123',
    ]);

    $mocked = createMockedSdkWithEvents();
    $sdk = $mocked['sdk'];
    $events = $mocked['events'];
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\EventsIngestResponse(
        contentType: 'application/json',
        statusCode: 202,
        rawResponse: $mockRawResponse,
    );
    $events->shouldReceive('ingest')
        ->once()
        ->andReturn($response);

    setLaravelPolarSdk($sdk);

    $user->ingestUsageEvent('api_request', [
        'endpoint' => '/api/v1/data',
        'method' => 'GET',
    ]);

    expect(true)->toBeTrue();
});

it('does not ingest event when customer is null', function () {
    $user = User::factory()->create();

    $user->ingestUsageEvent('api_request', []);

    expect(true)->toBeTrue();
});

it('does not ingest event when customer polar_id is null', function () {
    $user = User::factory()->create();
    Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => null,
    ]);

    $user->ingestUsageEvent('api_request', []);

    expect(true)->toBeTrue();
});

it('can ingest multiple usage events in batch', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'customer_123',
    ]);

    $mocked = createMockedSdkWithEvents();
    $sdk = $mocked['sdk'];
    $events = $mocked['events'];
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\EventsIngestResponse(
        contentType: 'application/json',
        statusCode: 202,
        rawResponse: $mockRawResponse,
    );
    $events->shouldReceive('ingest')
        ->once()
        ->andReturn($response);

    setLaravelPolarSdk($sdk);

    $user->ingestUsageEvents([
        [
            'eventName' => 'api_request',
            'metadata' => ['endpoint' => '/api/v1/data'],
        ],
        [
            'eventName' => 'storage_used',
            'metadata' => ['bytes' => 1048576],
            'timestamp' => new \DateTime(),
        ],
    ]);

    expect(true)->toBeTrue();
});

it('can list customer meters', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'customer_123',
    ]);

    $mocked = createMockedSdkWithCustomerMeters();
    $sdk = $mocked['sdk'];
    $customerMeters = $mocked['customerMeters'];
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $mockListResource = Mockery::mock(Components\ListResourceCustomerMeter::class);
    $response = new Operations\CustomerMetersListResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: $mockRawResponse,
        listResourceCustomerMeter: $mockListResource,
    );
    $generator = function () use ($response) {
        yield $response;
    };
    $customerMeters->shouldReceive('list')
        ->once()
        ->andReturn($generator());

    setLaravelPolarSdk($sdk);

    $result = $user->listCustomerMeters();

    expect($result)->toBeInstanceOf(Operations\CustomerMetersListResponse::class);
});

it('can list customer meters filtered by meter id', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'customer_123',
    ]);

    $mocked = createMockedSdkWithCustomerMeters();
    $sdk = $mocked['sdk'];
    $customerMeters = $mocked['customerMeters'];
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $mockListResource = Mockery::mock(Components\ListResourceCustomerMeter::class);
    $response = new Operations\CustomerMetersListResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: $mockRawResponse,
        listResourceCustomerMeter: $mockListResource,
    );
    $generator = function () use ($response) {
        yield $response;
    };
    $customerMeters->shouldReceive('list')
        ->once()
        ->andReturn($generator());

    setLaravelPolarSdk($sdk);

    $result = $user->listCustomerMeters('meter_123');

    expect($result)->toBeInstanceOf(Operations\CustomerMetersListResponse::class);
});

it('throws exception when listing meters without customer', function () {
    $user = User::factory()->create();

    expect(fn() => $user->listCustomerMeters())
        ->toThrow(\Exception::class, 'Customer not yet created in Polar.');
});

it('throws exception when listing meters without polar_id', function () {
    $user = User::factory()->create();
    Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => null,
    ]);

    expect(fn() => $user->listCustomerMeters())
        ->toThrow(\Exception::class, 'Customer not yet created in Polar.');
});

it('can ingest events via LaravelPolar facade', function () {
    $event = new Components\EventCreateCustomer(
        name: 'api_request',
        customerId: 'customer_123',
        timestamp: new \DateTime(),
        metadata: ['endpoint' => '/api/v1/data'],
    );

    $request = new Components\EventsIngest(events: [$event]);

    $mocked = createMockedSdkWithEvents();
    $sdk = $mocked['sdk'];
    $events = $mocked['events'];
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\EventsIngestResponse(
        contentType: 'application/json',
        statusCode: 202,
        rawResponse: $mockRawResponse,
    );
    $events->shouldReceive('ingest')
        ->once()
        ->andReturn($response);

    setLaravelPolarSdk($sdk);

    LaravelPolar::ingestEvents($request);

    expect(true)->toBeTrue();
});

it('throws exception when ingesting events fails', function () {
    $event = new Components\EventCreateCustomer(
        name: 'api_request',
        customerId: 'customer_123',
    );

    $request = new Components\EventsIngest(events: [$event]);

    $mocked = createMockedSdkWithEvents();
    $sdk = $mocked['sdk'];
    $events = $mocked['events'];
    $mockRawResponse = Mockery::mock(ResponseInterface::class);
    $response = new Operations\EventsIngestResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: $mockRawResponse,
    );
    $events->shouldReceive('ingest')
        ->once()
        ->andReturn($response);

    setLaravelPolarSdk($sdk);

    expect(fn() => LaravelPolar::ingestEvents($request))
        ->toThrow(Errors\APIException::class);
});

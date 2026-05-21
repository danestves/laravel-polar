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

function injectSdkProperty(string $property, object $mock): void
{
    $sdk = LaravelPolar::sdk();
    $reflection = new \ReflectionClass($sdk);
    $prop = $reflection->getProperty($property);
    $prop->setAccessible(true);
    $prop->setValue($sdk, $mock);
}

function createMockedSdkWithMetrics(): array
{
    $base = createBaseMockedSdk();
    setLaravelPolarSdk($base['sdk']);

    $metrics = Mockery::mock(\Polar\Metrics::class);
    injectSdkProperty('metrics', $metrics);

    return ['sdk' => $base['sdk'], 'metrics' => $metrics];
}

function createMockedSdkWithFiles(): array
{
    $base = createBaseMockedSdk();
    setLaravelPolarSdk($base['sdk']);

    $files = Mockery::mock(\Polar\Files::class);
    injectSdkProperty('files', $files);

    return ['sdk' => $base['sdk'], 'files' => $files];
}

function createMockedSdkWithOrganizations(): array
{
    $base = createBaseMockedSdk();
    setLaravelPolarSdk($base['sdk']);

    $organizations = Mockery::mock(\Polar\Organizations::class);
    injectSdkProperty('organizations', $organizations);

    return ['sdk' => $base['sdk'], 'organizations' => $organizations];
}

it('getMetrics returns the MetricsResponse on 200', function () {
    $mocked = createMockedSdkWithMetrics();

    $metricsMock = Mockery::mock(Components\MetricsResponse::class);
    $response = new Operations\MetricsGetResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        metricsResponse: $metricsMock,
    );

    $mocked['metrics']->shouldReceive('get')->once()->andReturn($response);

    $request = new Operations\MetricsGetRequest(
        startDate: \Brick\DateTime\LocalDate::of(2026, 1, 1),
        endDate: \Brick\DateTime\LocalDate::of(2026, 1, 31),
        interval: Components\TimeInterval::Day,
    );

    expect(LaravelPolar::getMetrics($request))->toBe($metricsMock);
});

it('getMetrics throws on non-200', function () {
    $mocked = createMockedSdkWithMetrics();

    $response = new Operations\MetricsGetResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        metricsResponse: null,
    );

    $mocked['metrics']->shouldReceive('get')->andReturn($response);

    $request = new Operations\MetricsGetRequest(
        startDate: \Brick\DateTime\LocalDate::of(2026, 1, 1),
        endDate: \Brick\DateTime\LocalDate::of(2026, 1, 31),
        interval: Components\TimeInterval::Day,
    );

    expect(fn() => LaravelPolar::getMetrics($request))
        ->toThrow(Errors\APIException::class);
});

it('listFiles returns the first 200 page from the generator', function () {
    $mocked = createMockedSdkWithFiles();

    $list = new Components\ListResourceFileRead(
        items: [],
        pagination: new Components\Pagination(totalCount: 0, maxPage: 1),
    );

    $response = new Operations\FilesListResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        listResourceFileRead: $list,
    );

    $mocked['files']->shouldReceive('list')->andReturn((function () use ($response) {
        yield $response;
    })());

    expect(LaravelPolar::listFiles())->toBe($response);
});

it('listOrganizations returns the first 200 page from the generator', function () {
    $mocked = createMockedSdkWithOrganizations();

    $list = new Components\ListResourceOrganization(
        items: [],
        pagination: new Components\Pagination(totalCount: 0, maxPage: 1),
    );

    $response = new Operations\OrganizationsListResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        listResourceOrganization: $list,
    );

    $mocked['organizations']->shouldReceive('list')->andReturn((function () use ($response) {
        yield $response;
    })());

    expect(LaravelPolar::listOrganizations())->toBe($response);
});

it('getOrganization returns the Organization on 200', function () {
    $mocked = createMockedSdkWithOrganizations();

    $orgMock = Mockery::mock(Components\Organization::class);
    $response = new Operations\OrganizationsGetResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        organization: $orgMock,
    );

    $mocked['organizations']->shouldReceive('get')->once()->andReturn($response);

    expect(LaravelPolar::getOrganization('org_abc'))->toBe($orgMock);
});

it('getOrganization throws on non-200', function () {
    $mocked = createMockedSdkWithOrganizations();

    $response = new Operations\OrganizationsGetResponse(
        contentType: 'application/json',
        statusCode: 404,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        organization: null,
    );

    $mocked['organizations']->shouldReceive('get')->andReturn($response);

    expect(fn() => LaravelPolar::getOrganization('org_missing'))
        ->toThrow(Errors\APIException::class);
});

<?php

use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function setLaravelPolarSdk(\Polar\Polar $sdk): void
{
    LaravelPolar::setSdk($sdk);
}

function resetLaravelPolarSdk(): void
{
    LaravelPolar::resetSdk();
}

function createBaseMockedSdk(): array
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

    return ['sdkConfig' => $sdkConfig, 'sdk' => new \Polar\Polar($sdkConfig)];
}

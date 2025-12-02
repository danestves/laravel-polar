<?php

use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function setLaravelPolarSdk(\Polar\Polar $sdk): void
{
    $reflection = new \ReflectionClass(LaravelPolar::class);
    $sdkProperty = $reflection->getProperty('sdkInstance');
    $sdkProperty->setAccessible(true);
    $sdkProperty->setValue(null, $sdk);
}

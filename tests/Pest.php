<?php

use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function setLaravelPolarSdk(\Polar\Polar $sdk): void
{
    LaravelPolar::setSdk($sdk);
}

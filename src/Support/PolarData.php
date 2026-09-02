<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Support;

use DateTimeInterface;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\Creation\CreationContextFactory;

abstract class PolarData extends Data
{
    /**
     * Build Polar data with support for every timestamp format returned by the API.
     */
    public static function factory(?CreationContext $creationContext = null): CreationContextFactory
    {
        return parent::factory($creationContext)->withCast(
            DateTimeInterface::class,
            new DateTimeInterfaceCast([
                DATE_ATOM,
                'Y-m-d\TH:i:s.uP',
            ]),
        );
    }
}

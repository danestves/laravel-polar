<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Billable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BenefitGrantCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The billable entity.
         */
        public Billable $billable, // @phpstan-ignore-line parameter.trait, property.trait - Billable is used in the user final code
        /**
         * The payload array.
         *
         * @var array<string, mixed>
         */
        public array $payload,
    ) {}
}

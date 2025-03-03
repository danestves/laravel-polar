<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Contracts\Billable;
use Danestves\LaravelPolar\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCanceled
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
        public Billable $billable,
        /**
         * The subscription instance.
         */
        public Subscription $subscription,
        /**
         * The payload array.
         *
         * @var array<string, mixed>
         */
        public array $payload,
    ) {}
}

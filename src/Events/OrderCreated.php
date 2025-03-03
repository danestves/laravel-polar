<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Contracts\Billable;
use Danestves\LaravelPolar\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated
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
         * The order entity.
         */
        public Order $order,
        /**
         * The payload array.
         */
        public array $payload,
    ) {}
}

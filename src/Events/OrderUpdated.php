<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderUpdated
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
        public Model $billable,
        /**
         * The order entity.
         */
        public Order $order,
        /**
         * The payload array.
         */
        public array $payload,
        /**
         * Whether the order is refunded.
         */
        public bool $isRefunded,
    ) {}
}

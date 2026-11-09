<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Data\WebhookOrderRefundedPayload;
use Danestves\LaravelPolar\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderRefunded
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
         * The webhook payload.
         */
        public WebhookOrderRefundedPayload $payload,
    ) {}
}

<?php

namespace Danestves\LaravelPolar\Events;

use Polar\Models\Components\WebhookCustomerCreatedPayload;

class CustomerCreated extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookCustomerCreatedPayload $payload,
    ) {}
}

<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Data\WebhookCustomerSeatAssignedPayload;

class CustomerSeatAssigned extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookCustomerSeatAssignedPayload $payload,
    ) {}
}

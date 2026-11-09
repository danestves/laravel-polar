<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Data\WebhookCustomerSeatClaimedPayload;

class CustomerSeatClaimed extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookCustomerSeatClaimedPayload $payload,
    ) {}
}

<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Data\WebhookCustomerUpdatedPayload;

class CustomerUpdated extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookCustomerUpdatedPayload $payload,
    ) {}
}

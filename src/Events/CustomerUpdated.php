<?php

namespace Danestves\LaravelPolar\Events;

use Polar\Models\Components\WebhookCustomerUpdatedPayload;

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

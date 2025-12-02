<?php

namespace Danestves\LaravelPolar\Events;

use Polar\Models\Components\WebhookCustomerDeletedPayload;

class CustomerDeleted extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookCustomerDeletedPayload $payload,
    ) {}
}

<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Data\WebhookCustomerSeatRevokedPayload;

class CustomerSeatRevoked extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookCustomerSeatRevokedPayload $payload,
    ) {}
}

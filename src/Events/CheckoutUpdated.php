<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Data\WebhookCheckoutUpdatedPayload;

class CheckoutUpdated extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookCheckoutUpdatedPayload $payload,
    ) {}
}

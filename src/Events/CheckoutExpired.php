<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Data\WebhookCheckoutExpiredPayload;

class CheckoutExpired extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookCheckoutExpiredPayload $payload,
    ) {}
}

<?php

namespace Danestves\LaravelPolar\Events;

use Polar\Models\Components\WebhookCheckoutExpiredPayload;

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

<?php

namespace Danestves\LaravelPolar\Events;

use Polar\Models\Components\WebhookProductCreatedPayload;

class ProductCreated extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookProductCreatedPayload $payload,
    ) {}
}

<?php

namespace Danestves\LaravelPolar\Events;

use Polar\Models\Components\WebhookProductUpdatedPayload;

class ProductUpdated extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookProductUpdatedPayload $payload,
    ) {}
}

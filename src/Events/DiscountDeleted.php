<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Data\WebhookDiscountDeletedPayload;

class DiscountDeleted extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookDiscountDeletedPayload $payload,
    ) {}
}

<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Data\WebhookBenefitCreatedPayload;

class BenefitCreated extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookBenefitCreatedPayload $payload,
    ) {}
}

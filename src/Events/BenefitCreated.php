<?php

namespace Danestves\LaravelPolar\Events;

use Polar\Models\Components\WebhookBenefitCreatedPayload;

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

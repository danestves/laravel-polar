<?php

namespace Danestves\LaravelPolar\Events;

use Polar\Models\Components\WebhookBenefitUpdatedPayload;

class BenefitUpdated extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookBenefitUpdatedPayload $payload,
    ) {}
}

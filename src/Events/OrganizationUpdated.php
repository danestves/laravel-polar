<?php

namespace Danestves\LaravelPolar\Events;

use Danestves\LaravelPolar\Data\WebhookOrganizationUpdatedPayload;

class OrganizationUpdated extends WebhookEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookOrganizationUpdatedPayload $payload,
    ) {}
}

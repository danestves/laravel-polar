<?php

namespace Danestves\LaravelPolar\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polar\Models\Components\WebhookCustomerDeletedPayload;

class CustomerDeleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The webhook payload.
         */
        public WebhookCustomerDeletedPayload $payload,
    ) {}
}

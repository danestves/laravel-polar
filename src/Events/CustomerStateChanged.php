<?php

namespace Danestves\LaravelPolar\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polar\Models\Components\WebhookCustomerStateChangedPayload;

class CustomerStateChanged
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
        public WebhookCustomerStateChangedPayload $payload,
    ) {}
}

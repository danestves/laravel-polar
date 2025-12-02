<?php

namespace Danestves\LaravelPolar\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polar\Models\Components\WebhookCheckoutCreatedPayload;

class CheckoutCreated
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
        public WebhookCheckoutCreatedPayload $payload,
    ) {}
}

<?php

namespace Danestves\LaravelPolar\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Danestves\LaravelPolar\Data\WebhookBenefitGrantUpdatedPayload;

class BenefitGrantUpdated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        /**
         * The billable entity.
         */
        public Model $billable,
        /**
         * The webhook payload.
         */
        public WebhookBenefitGrantUpdatedPayload $payload,
    ) {}
}

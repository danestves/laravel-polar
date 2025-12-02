<?php

namespace Danestves\LaravelPolar\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polar\Models\Components\WebhookBenefitGrantRevokedPayload;

class BenefitGrantRevoked
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
        public WebhookBenefitGrantRevokedPayload $payload,
    ) {}
}

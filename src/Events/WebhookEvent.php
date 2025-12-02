<?php

namespace Danestves\LaravelPolar\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class WebhookEvent
{
    use Dispatchable;
    use SerializesModels;
}

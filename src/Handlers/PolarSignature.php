<?php

namespace Danestves\LaravelPolar\Handlers;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class PolarSignature implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $signingSecret = base64_encode($config->signingSecret);
        $wh = new \StandardWebhooks\Webhook($signingSecret);

        return (bool) ($wh->verify($request->getContent(), [
            'webhook-id' => $request->header('webhook-id'),
            'webhook-signature' => $request->header('webhook-signature'),
            'webhook-timestamp' => $request->header('webhook-timestamp'),
        ]));
    }
}

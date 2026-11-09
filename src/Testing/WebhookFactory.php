<?php

namespace Danestves\LaravelPolar\Testing;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use StandardWebhooks\Webhook;

/**
 * Build a correctly signed Polar webhook request for feature tests.
 *
 * The payload is signed exactly the way {@see \Danestves\LaravelPolar\Handlers\PolarSignature}
 * verifies it, so a generated body can be posted straight at the package's webhook route:
 *
 *     $webhook = WebhookFactory::make('order.created', $orderData);
 *
 *     $this->withHeaders($webhook['headers'])
 *         ->call('POST', '/polar/webhook', content: $webhook['body']);
 */
class WebhookFactory
{
    /**
     * Build the signed payload, headers and raw JSON body.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     payload: array{type: string, data: array<string, mixed>, timestamp: string},
     *     headers: array{webhook-id: string, webhook-timestamp: string, webhook-signature: string},
     *     body: string
     * }
     */
    public static function make(
        string $type,
        array $data,
        ?string $secret = null,
        ?int $timestamp = null,
        ?string $id = null,
    ): array {
        $timestamp ??= time();
        $id ??= 'msg_' . bin2hex(random_bytes(8));

        $payload = [
            'type' => $type,
            'data' => $data,
            'timestamp' => gmdate('Y-m-d\TH:i:s.000000\Z', $timestamp),
        ];

        $body = json_encode($payload);

        if ($body === false) {
            throw new InvalidArgumentException('Unable to JSON-encode the Polar webhook payload.');
        }

        $webhook = new Webhook(base64_encode(self::resolveSecret($secret)));

        return [
            'payload' => $payload,
            'headers' => [
                'content-type' => 'application/json',
                'webhook-id' => $id,
                'webhook-timestamp' => (string) $timestamp,
                'webhook-signature' => $webhook->sign($id, $timestamp, $body),
            ],
            'body' => $body,
        ];
    }

    /**
     * Convenience helper: sign a payload and POST it to a URL.
     *
     * @param  array<string, mixed>  $data
     */
    public static function send(
        string $url,
        string $type,
        array $data,
        ?string $secret = null,
        ?int $timestamp = null,
        ?string $id = null,
    ): Response {
        $webhook = self::make($type, $data, $secret, $timestamp, $id);

        return Http::withHeaders($webhook['headers'])
            ->withBody($webhook['body'], 'application/json')
            ->post($url);
    }

    private static function resolveSecret(?string $secret): string
    {
        // PolarSignature verifies with the webhook-client signing secret, so prefer the same
        // value here to keep generated signatures valid even when the two config keys differ.
        foreach ([
            $secret,
            config('webhook-client.configs.0.signing_secret'),
            config('polar.webhook_secret'),
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        throw new InvalidArgumentException(
            'A Polar webhook secret is required. Pass one explicitly or set webhook-client.configs.0.signing_secret / polar.webhook_secret.',
        );
    }
}

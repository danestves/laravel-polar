<?php

use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Testing\PolarFake;
use Illuminate\Support\Facades\Event;
use StandardWebhooks\Webhook;

it('exposes the fake through the facade', function () {
    $fake = LaravelPolar::fake([
        'GET /v1/products/' => [
            'items' => [],
            'pagination' => ['total_count' => 0, 'max_page' => 1],
        ],
    ]);

    expect($fake)->toBeInstanceOf(PolarFake::class);

    LaravelPolar::listProducts();

    $fake->assertSent('GET', '/v1/products/');
    $fake->assertSentCount(1);
});

it('signs a fake webhook with the configured secret', function () {
    config()->set('polar.webhook_secret', 'whsec_test_secret');

    $webhook = LaravelPolar::fakeWebhook('subscription.created', ['id' => 'sub_123']);

    expect($webhook['payload']['type'])->toBe('subscription.created')
        ->and($webhook['payload']['data']['id'])->toBe('sub_123')
        ->and($webhook['headers'])->toHaveKeys(['webhook-id', 'webhook-timestamp', 'webhook-signature'])
        ->and($webhook['body'])->toBeJson();

    $verifier = new Webhook(base64_encode('whsec_test_secret'));

    expect($verifier->verify($webhook['body'], $webhook['headers']))
        ->toBe(['type' => 'subscription.created', 'data' => ['id' => 'sub_123'], 'timestamp' => $webhook['payload']['timestamp']]);
});

it('posts a fake webhook through the package route', function () {
    $this->withoutExceptionHandling();
    Event::fake();
    \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
        'users' => \Danestves\LaravelPolar\Tests\Fixtures\User::class,
    ]);

    $user = \Danestves\LaravelPolar\Tests\Fixtures\User::factory()->create();

    config()->set('polar.webhook_secret', 'whsec_test_secret');
    config()->set('webhook-client.configs.0.signing_secret', 'whsec_test_secret');
    config()->set('queue.default', 'sync');

    $data = polarFixture('Order', [
        'id' => 'order_webhook_1',
        'status' => 'paid',
        'customer' => [
            'id' => 'customer_123',
            'metadata' => [
                'billable_id' => (string) $user->getKey(),
                'billable_type' => $user->getMorphClass(),
            ],
        ],
    ]);

    $webhook = LaravelPolar::fakeWebhook('order.created', $data);

    $this->call(
        'POST',
        '/polar/webhook',
        server: $this->transformHeadersToServerVars($webhook['headers']),
        content: $webhook['body'],
    )->assertOk();

    expect(\Danestves\LaravelPolar\Order::where('polar_id', 'order_webhook_1')->exists())->toBeTrue();
    Event::assertDispatched(\Danestves\LaravelPolar\Events\OrderCreated::class);
});

it('registers the polar:sync command', function () {
    $this->artisan('polar:sync', ['--resource' => 'nope'])
        ->expectsOutputToContain('Invalid resource')
        ->assertExitCode(1);
});

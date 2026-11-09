<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Enums\WebhookFormat;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Support\Facades\Http;

it('lists webhook endpoints', function () {
    fakePolarList('v1/webhooks/endpoints*', [polarFixture('WebhookEndpoint')]);

    $page = LaravelPolar::listWebhooks();

    expect($page)->toHaveCount(1)
        ->and($page->first())->toBeInstanceOf(Data\WebhookEndpoint::class)
        ->and($page->first()->enabled)->toBeTrue();
});

it('creates a webhook endpoint', function () {
    fakePolar('v1/webhooks/endpoints', polarFixture('WebhookEndpoint', ['id' => 'wh_1']), 201);

    $endpoint = LaravelPolar::createWebhook(new Data\WebhookEndpointCreate(
        url: 'https://example.com/webhook',
        format: WebhookFormat::Raw,
        events: ['order.created'],
    ));

    expect($endpoint->id)->toBe('wh_1');

    Http::assertSent(fn($request) => $request->method() === 'POST'
        && $request['url'] === 'https://example.com/webhook'
        && $request['format'] === 'raw'
        && $request['events'] === ['order.created']);
});

it('gets, updates and rotates a webhook endpoint', function () {
    Http::fake([
        polarUrl('v1/webhooks/endpoints/wh_1') => Http::response(polarFixture('WebhookEndpoint', ['id' => 'wh_1', 'name' => 'Orders'])),
        polarUrl('v1/webhooks/endpoints/wh_1/secret') => Http::response(polarFixture('WebhookEndpoint', ['id' => 'wh_1', 'secret' => 'whsec_new'])),
    ]);

    expect(LaravelPolar::getWebhook('wh_1')->id)->toBe('wh_1');

    $updated = LaravelPolar::updateWebhook('wh_1', new Data\WebhookEndpointUpdate(name: 'Orders'));
    expect($updated->name)->toBe('Orders');

    expect(LaravelPolar::rotateWebhookSecret('wh_1')->secret)->toBe('whsec_new');

    Http::assertSent(fn($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/v1/webhooks/endpoints/wh_1'));
    Http::assertSent(fn($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/v1/webhooks/endpoints/wh_1')
        && $request['name'] === 'Orders');
    Http::assertSent(fn($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/v1/webhooks/endpoints/wh_1/secret'));
});

it('deletes a webhook endpoint', function () {
    fakePolar('v1/webhooks/endpoints/wh_1', [], 204);

    LaravelPolar::deleteWebhook('wh_1');

    Http::assertSent(fn($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/v1/webhooks/endpoints/wh_1'));
});

it('lists deliveries and redelivers an event', function () {
    Http::fake([
        polarUrl('v1/webhooks/deliveries*') => Http::response([
            'items' => [polarFixture('WebhookDelivery')],
            'pagination' => ['total_count' => 1, 'max_page' => 1],
        ]),
        polarUrl('v1/webhooks/events/evt_1/redeliver') => Http::response([], 202),
    ]);

    $deliveries = LaravelPolar::listWebhookDeliveries(['succeeded' => 'true']);

    expect($deliveries)->toHaveCount(1)
        ->and($deliveries->first()->webhookEvent->type->value)->toBe('order.created');

    LaravelPolar::redeliverWebhookEvent('evt_1');

    Http::assertSent(fn($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/v1/webhooks/events/evt_1/redeliver'));
});

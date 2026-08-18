<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

/**
 * A billable with a Polar customer already attached.
 */
function meteredUser(?string $polarId = 'cus_1'): User
{
    $user = User::factory()->create();
    $user->createAsCustomer(['polar_id' => $polarId]);

    return $user->refresh();
}

it('ingests a single usage event for the billable', function () {
    fakePolar('v1/events/ingest', polarFixture('EventsIngestResponse', ['inserted' => 1]));

    meteredUser()->ingestUsageEvent('api.call', ['endpoint' => '/v1/things']);

    Http::assertSent(function ($request) {
        $event = $request['events'][0];

        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/v1/events/ingest')
            && $event['name'] === 'api.call'
            && $event['customer_id'] === 'cus_1'
            && $event['metadata'] === ['endpoint' => '/v1/things']
            && isset($event['timestamp']);
    });
});

it('omits metadata when none is given', function () {
    fakePolar('v1/events/ingest', polarFixture('EventsIngestResponse'));

    meteredUser()->ingestUsageEvent('api.call');

    Http::assertSent(fn($request) => ! array_key_exists('metadata', $request['events'][0]));
});

it('ingests a batch of usage events', function () {
    fakePolar('v1/events/ingest', polarFixture('EventsIngestResponse', ['inserted' => 2]));

    meteredUser()->ingestUsageEvents([
        ['eventName' => 'api.call', 'metadata' => ['endpoint' => '/a']],
        ['eventName' => 'api.call', 'timestamp' => new \DateTimeImmutable('2026-01-01T00:00:00+00:00')],
    ]);

    Http::assertSent(function ($request) {
        $events = $request['events'];

        return count($events) === 2
            && $events[0]['metadata'] === ['endpoint' => '/a']
            && str_starts_with($events[1]['timestamp'], '2026-01-01T00:00:00');
    });
});

it('skips ingestion when the billable has no Polar customer yet', function (?string $polarId) {
    $user = $polarId === null ? User::factory()->create() : meteredUser(null);

    $user->ingestUsageEvent('api.call');
    $user->ingestUsageEvents([['eventName' => 'api.call']]);

    Http::assertNothingSent();
})->with([
    'no customer record' => [null],
    'customer without polar_id' => ['unset'],
]);

it('sends nothing for an empty batch', function () {
    meteredUser()->ingestUsageEvents([]);

    Http::assertNothingSent();
});

it('lists the billable\'s customer meters', function () {
    fakePolarList('v1/customer-meters/*', [polarFixture('CustomerMeter', ['id' => 'cm_1'])]);

    $page = meteredUser()->listCustomerMeters();

    expect($page->first())->toBeInstanceOf(Data\CustomerMeter::class)
        ->and($page->first()->id)->toBe('cm_1');

    Http::assertSent(fn($request) => str_contains($request->url(), 'customer_id=cus_1'));
});

it('filters customer meters by meter id', function () {
    fakePolarList('v1/customer-meters/*', []);

    meteredUser()->listCustomerMeters('meter_1');

    Http::assertSent(fn($request) => str_contains($request->url(), 'meter_id=meter_1'));
});

it('refuses to list meters before the billable has a Polar customer', function () {
    expect(fn() => User::factory()->create()->listCustomerMeters())
        ->toThrow(\Exception::class, 'Customer not yet created in Polar.');
});

it('ingests events through the facade', function () {
    fakePolar('v1/events/ingest', polarFixture('EventsIngestResponse', ['inserted' => 1]));

    $response = LaravelPolar::ingestEvents(new Data\EventsIngest(events: [
        new Data\EventCreateCustomer(name: 'api.call', customerId: 'cus_1'),
    ]));

    expect($response)->toBeInstanceOf(Data\EventsIngestResponse::class)
        ->and($response->inserted)->toBe(1);
});

it('accepts any 2xx from the ingest endpoint', function (int $status) {
    fakePolar('v1/events/ingest', polarFixture('EventsIngestResponse'), $status);

    LaravelPolar::ingestEvents(['events' => []]);

    Http::assertSentCount(1);
})->with([200, 202]);

it('raises on a failed ingest', function () {
    fakePolar('v1/events/ingest', ['detail' => 'Bad event'], 422);

    expect(fn() => LaravelPolar::ingestEvents(['events' => []]))
        ->toThrow(\Danestves\LaravelPolar\Exceptions\PolarApiError::class);
});

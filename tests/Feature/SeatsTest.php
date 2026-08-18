<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Subscription;
use Illuminate\Support\Facades\Http;

it('lists the seats on a subscription', function () {
    fakePolar('v1/customer-seats*', polarFixture('SeatsList', [
        'available_seats' => 3,
        'total_seats' => 5,
    ]));

    $seats = LaravelPolar::listSeats(subscriptionId: 'sub_1');

    expect($seats)->toBeInstanceOf(Data\SeatsList::class)
        ->and($seats->availableSeats)->toBe(3)
        ->and($seats->totalSeats)->toBe(5);

    Http::assertSent(fn($request) => str_contains($request->url(), 'subscription_id=sub_1'));
});

it('assigns a seat', function () {
    fakePolar('v1/customer-seats', polarFixture('CustomerSeat', ['id' => 'seat_1']));

    $seat = LaravelPolar::assignSeat(new Data\SeatAssign(
        subscriptionId: 'sub_1',
        email: 'member@example.com',
    ));

    expect($seat->id)->toBe('seat_1');

    Http::assertSent(fn($request) => $request->method() === 'POST'
        && $request['subscription_id'] === 'sub_1'
        && $request['email'] === 'member@example.com');
});

it('revokes a seat', function () {
    fakePolar('v1/customer-seats/seat_1', polarFixture('CustomerSeat', ['id' => 'seat_1']));

    expect(LaravelPolar::revokeSeat('seat_1')->id)->toBe('seat_1');

    Http::assertSent(fn($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/v1/customer-seats/seat_1'));
});

it('resends a seat invitation', function () {
    fakePolar('v1/customer-seats/seat_1/resend', polarFixture('CustomerSeat', ['id' => 'seat_1']));

    expect(LaravelPolar::resendSeatInvitation('seat_1')->id)->toBe('seat_1');

    Http::assertSent(fn($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/v1/customer-seats/seat_1/resend'));
});

it('scopes a subscription\'s seat calls to that subscription', function () {
    fakePolar('v1/customer-seats*', polarFixture('SeatsList'));

    $subscription = Subscription::factory()->create(['polar_id' => 'sub_1']);
    $subscription->seats();

    Http::assertSent(fn($request) => str_contains($request->url(), 'subscription_id=sub_1'));
});

it('assigns a seat through the subscription, forwarding every identifier', function () {
    fakePolar('v1/customer-seats', polarFixture('CustomerSeat', ['id' => 'seat_1']));

    $subscription = Subscription::factory()->create(['polar_id' => 'sub_1']);

    $subscription->assignSeat(
        email: 'member@example.com',
        customerId: 'cus_1',
        externalCustomerId: 'ext_1',
        metadata: ['team' => 'growth'],
    );

    Http::assertSent(fn($request) => $request['subscription_id'] === 'sub_1'
        && $request['email'] === 'member@example.com'
        && $request['customer_id'] === 'cus_1'
        && $request['external_customer_id'] === 'ext_1'
        && $request['metadata'] === ['team' => 'growth']);
});

it('revokes and resends through the subscription', function () {
    Http::fake([
        polarUrl('v1/customer-seats/seat_1') => Http::response(polarFixture('CustomerSeat', ['id' => 'seat_1'])),
        polarUrl('v1/customer-seats/seat_1/resend') => Http::response(polarFixture('CustomerSeat', ['id' => 'seat_1'])),
    ]);

    $subscription = Subscription::factory()->create(['polar_id' => 'sub_1']);

    expect($subscription->revokeSeat('seat_1')->id)->toBe('seat_1')
        ->and($subscription->resendSeatInvitation('seat_1')->id)->toBe('seat_1');
});

<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Subscription;
use Illuminate\Support\Facades\Config;
use Mockery;
use Polar\Models\Components;
use Polar\Models\Errors;
use Polar\Models\Operations;

beforeEach(function () {
    Config::set('polar.access_token', 'test-token');
    Config::set('polar.server', 'sandbox');
});

afterEach(function () {
    resetLaravelPolarSdk();
    Mockery::close();
});

function createMockedSdkWithCustomerSeats(): array
{
    $base = createBaseMockedSdk();
    $sdk = $base['sdk'];

    $customerSeats = Mockery::mock(\Polar\CustomerSeats::class);
    $reflectionSdk = new \ReflectionClass($sdk);
    $property = $reflectionSdk->getProperty('customerSeats');
    $property->setAccessible(true);
    $property->setValue($sdk, $customerSeats);

    return ['sdk' => $sdk, 'customerSeats' => $customerSeats];
}

it('listSeats returns the SeatsList on 200', function () {
    $mocked = createMockedSdkWithCustomerSeats();
    setLaravelPolarSdk($mocked['sdk']);

    $list = new Components\SeatsList(seats: [], availableSeats: 5, totalSeats: 10);
    $response = new Operations\CustomerSeatsListSeatsResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        seatsList: $list,
    );

    $mocked['customerSeats']->shouldReceive('listSeats')
        ->once()
        ->andReturn($response);

    expect(LaravelPolar::listSeats(subscriptionId: 'sub_xxx'))->toBe($list);
});

it('assignSeat returns the CustomerSeat on 200', function () {
    $mocked = createMockedSdkWithCustomerSeats();
    setLaravelPolarSdk($mocked['sdk']);

    $seat = Mockery::mock(Components\CustomerSeat::class);
    $response = new Operations\CustomerSeatsAssignSeatResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        customerSeat: $seat,
    );

    $mocked['customerSeats']->shouldReceive('assignSeat')
        ->once()
        ->withArgs(fn(Components\SeatAssign $body) => $body->subscriptionId === 'sub_xxx' && $body->email === 'alice@example.com')
        ->andReturn($response);

    expect(LaravelPolar::assignSeat(new Components\SeatAssign(subscriptionId: 'sub_xxx', email: 'alice@example.com')))->toBe($seat);
});

it('revokeSeat returns the CustomerSeat on 200', function () {
    $mocked = createMockedSdkWithCustomerSeats();
    setLaravelPolarSdk($mocked['sdk']);

    $seat = Mockery::mock(Components\CustomerSeat::class);
    $response = new Operations\CustomerSeatsRevokeSeatResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        customerSeat: $seat,
    );

    $mocked['customerSeats']->shouldReceive('revokeSeat')->once()->andReturn($response);

    expect(LaravelPolar::revokeSeat('seat_xxx'))->toBe($seat);
});

it('resendSeatInvitation returns the CustomerSeat on 200', function () {
    $mocked = createMockedSdkWithCustomerSeats();
    setLaravelPolarSdk($mocked['sdk']);

    $seat = Mockery::mock(Components\CustomerSeat::class);
    $response = new Operations\CustomerSeatsResendInvitationResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        customerSeat: $seat,
    );

    $mocked['customerSeats']->shouldReceive('resendInvitation')->once()->andReturn($response);

    expect(LaravelPolar::resendSeatInvitation('seat_xxx'))->toBe($seat);
});

it('Subscription::seats calls listSeats with this subscription id', function () {
    $mocked = createMockedSdkWithCustomerSeats();
    setLaravelPolarSdk($mocked['sdk']);

    $subscription = Subscription::factory()->active()->create(['polar_id' => 'sub_team']);

    $list = new Components\SeatsList(seats: [], availableSeats: 3, totalSeats: 5);
    $response = new Operations\CustomerSeatsListSeatsResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        seatsList: $list,
    );

    $mocked['customerSeats']->shouldReceive('listSeats')
        ->once()
        ->withArgs(fn(?string $subId, ?string $orderId) => $subId === 'sub_team' && $orderId === null)
        ->andReturn($response);

    expect($subscription->seats())->toBe($list);
});

it('Subscription::assignSeat forwards email, customerId, externalCustomerId, and metadata', function () {
    $mocked = createMockedSdkWithCustomerSeats();
    setLaravelPolarSdk($mocked['sdk']);

    $subscription = Subscription::factory()->active()->create(['polar_id' => 'sub_team']);

    $seat = Mockery::mock(Components\CustomerSeat::class);
    $response = new Operations\CustomerSeatsAssignSeatResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        customerSeat: $seat,
    );

    $mocked['customerSeats']->shouldReceive('assignSeat')
        ->once()
        ->withArgs(function (Components\SeatAssign $body) {
            return $body->subscriptionId === 'sub_team'
                && $body->email === 'alice@example.com'
                && $body->customerId === 'cust_xx'
                && $body->externalCustomerId === 'ext_yy'
                && $body->metadata === ['role' => 'admin'];
        })
        ->andReturn($response);

    $subscription->assignSeat(
        email: 'alice@example.com',
        customerId: 'cust_xx',
        externalCustomerId: 'ext_yy',
        metadata: ['role' => 'admin'],
    );
});

it('Subscription::revokeSeat proxies to LaravelPolar::revokeSeat', function () {
    $mocked = createMockedSdkWithCustomerSeats();
    setLaravelPolarSdk($mocked['sdk']);

    $subscription = Subscription::factory()->active()->create(['polar_id' => 'sub_team']);

    $seat = Mockery::mock(Components\CustomerSeat::class);
    $response = new Operations\CustomerSeatsRevokeSeatResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        customerSeat: $seat,
    );

    $mocked['customerSeats']->shouldReceive('revokeSeat')
        ->once()
        ->withArgs(fn(string $seatId) => $seatId === 'seat_revoke')
        ->andReturn($response);

    expect($subscription->revokeSeat('seat_revoke'))->toBe($seat);
});

it('Subscription::resendSeatInvitation proxies to LaravelPolar::resendSeatInvitation', function () {
    $mocked = createMockedSdkWithCustomerSeats();
    setLaravelPolarSdk($mocked['sdk']);

    $subscription = Subscription::factory()->active()->create(['polar_id' => 'sub_team']);

    $seat = Mockery::mock(Components\CustomerSeat::class);
    $response = new Operations\CustomerSeatsResendInvitationResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        customerSeat: $seat,
    );

    $mocked['customerSeats']->shouldReceive('resendInvitation')
        ->once()
        ->withArgs(fn(string $seatId) => $seatId === 'seat_resend')
        ->andReturn($response);

    expect($subscription->resendSeatInvitation('seat_resend'))->toBe($seat);
});

it('listSeats throws when SDK returns non-200', function () {
    $mocked = createMockedSdkWithCustomerSeats();
    setLaravelPolarSdk($mocked['sdk']);

    $response = new Operations\CustomerSeatsListSeatsResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        seatsList: null,
    );

    $mocked['customerSeats']->shouldReceive('listSeats')->andReturn($response);

    expect(fn() => LaravelPolar::listSeats(subscriptionId: 'sub_xxx'))
        ->toThrow(Errors\APIException::class);
});

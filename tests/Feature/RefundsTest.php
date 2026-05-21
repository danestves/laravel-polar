<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Order;
use Illuminate\Support\Facades\Config;
use Mockery;
use Polar\Models\Components;
use Polar\Models\Operations;

beforeEach(function () {
    Config::set('polar.access_token', 'test-token');
    Config::set('polar.server', 'sandbox');
});

afterEach(function () {
    resetLaravelPolarSdk();
    Mockery::close();
});

function createMockedSdkWithRefunds(): array
{
    $base = createBaseMockedSdk();
    $sdk = $base['sdk'];

    $refunds = Mockery::mock(\Polar\Refunds::class);
    $reflectionSdk = new \ReflectionClass($sdk);
    $refundsProperty = $reflectionSdk->getProperty('refunds');
    $refundsProperty->setAccessible(true);
    $refundsProperty->setValue($sdk, $refunds);

    return ['sdk' => $sdk, 'refunds' => $refunds];
}

it('forwards createRefund to the SDK and returns the refund on success', function () {
    $mocked = createMockedSdkWithRefunds();
    setLaravelPolarSdk($mocked['sdk']);

    $refundMock = Mockery::mock(Components\Refund::class);
    $response = new Operations\RefundsCreateResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        refund: $refundMock,
    );

    $request = new Components\RefundCreate(
        orderId: 'ord_123',
        reason: Components\RefundReason::CustomerRequest,
        amount: 5000,
    );

    $mocked['refunds']->shouldReceive('create')
        ->once()
        ->withArgs(function ($body) {
            return $body instanceof Components\RefundCreate
                && $body->orderId === 'ord_123'
                && $body->reason === Components\RefundReason::CustomerRequest
                && $body->amount === 5000;
        })
        ->andReturn($response);

    $result = LaravelPolar::createRefund($request);

    expect($result)->toBe($refundMock);
});

it('throws when createRefund SDK call returns a non-200 status', function () {
    $mocked = createMockedSdkWithRefunds();
    setLaravelPolarSdk($mocked['sdk']);

    $response = new Operations\RefundsCreateResponse(
        contentType: 'application/json',
        statusCode: 500,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        refund: null,
    );

    $mocked['refunds']->shouldReceive('create')->andReturn($response);

    $request = new Components\RefundCreate(
        orderId: 'ord_123',
        reason: Components\RefundReason::Other,
        amount: 100,
    );

    expect(fn() => LaravelPolar::createRefund($request))
        ->toThrow(\Polar\Models\Errors\APIException::class);
});

it('listRefunds returns the first 200 response from the paginated generator', function () {
    $mocked = createMockedSdkWithRefunds();
    setLaravelPolarSdk($mocked['sdk']);

    $list = new Components\ListResourceRefund(
        items: [Mockery::mock(Components\Refund::class)],
        pagination: new Components\Pagination(totalCount: 1, maxPage: 1),
    );

    $response = new Operations\RefundsListResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        listResourceRefund: $list,
    );

    $mocked['refunds']->shouldReceive('list')->andReturn((function () use ($response) {
        yield $response;
    })());

    $result = LaravelPolar::listRefunds();

    expect($result)->toBe($response);
    expect($result->listResourceRefund?->items)->toHaveCount(1);
});

it('Order::refund forwards to LaravelPolar::createRefund with defaults filled from the order', function () {
    $mocked = createMockedSdkWithRefunds();
    setLaravelPolarSdk($mocked['sdk']);

    $order = Order::factory()->paid()->create([
        'polar_id' => 'ord_abc',
        'amount' => 10000,
        'refunded_amount' => 3000,
    ]);

    $refundMock = Mockery::mock(Components\Refund::class);
    $response = new Operations\RefundsCreateResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        refund: $refundMock,
    );

    $mocked['refunds']->shouldReceive('create')
        ->once()
        ->withArgs(function ($body) {
            return $body instanceof Components\RefundCreate
                && $body->orderId === 'ord_abc'
                && $body->reason === Components\RefundReason::CustomerRequest
                && $body->amount === 7000;
        })
        ->andReturn($response);

    $result = $order->refund();

    expect($result)->toBe($refundMock);
});

it('Order::refund honors a provided amount, reason, comment, and metadata', function () {
    $mocked = createMockedSdkWithRefunds();
    setLaravelPolarSdk($mocked['sdk']);

    $order = Order::factory()->paid()->create([
        'polar_id' => 'ord_def',
        'amount' => 10000,
        'refunded_amount' => 0,
    ]);

    $refundMock = Mockery::mock(Components\Refund::class);
    $response = new Operations\RefundsCreateResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        refund: $refundMock,
    );

    $mocked['refunds']->shouldReceive('create')
        ->once()
        ->withArgs(function ($body) {
            return $body instanceof Components\RefundCreate
                && $body->orderId === 'ord_def'
                && $body->amount === 2500
                && $body->reason === Components\RefundReason::Fraudulent
                && $body->comment === 'flagged by risk team'
                && $body->metadata === ['ticket' => 'T-42'];
        })
        ->andReturn($response);

    $order->refund(
        amount: 2500,
        reason: Components\RefundReason::Fraudulent,
        comment: 'flagged by risk team',
        metadata: ['ticket' => 'T-42'],
    );
});

it('Order::refunds returns a Collection of Refund items for this order', function () {
    $mocked = createMockedSdkWithRefunds();
    setLaravelPolarSdk($mocked['sdk']);

    $order = Order::factory()->paid()->create([
        'polar_id' => 'ord_ghi',
    ]);

    $refund1 = Mockery::mock(Components\Refund::class);
    $refund2 = Mockery::mock(Components\Refund::class);

    $list = new Components\ListResourceRefund(
        items: [$refund1, $refund2],
        pagination: new Components\Pagination(totalCount: 2, maxPage: 1),
    );

    $response = new Operations\RefundsListResponse(
        contentType: 'application/json',
        statusCode: 200,
        rawResponse: Mockery::mock(\Psr\Http\Message\ResponseInterface::class),
        listResourceRefund: $list,
    );

    $mocked['refunds']->shouldReceive('list')
        ->once()
        ->withArgs(function ($request) {
            return $request instanceof Operations\RefundsListRequest
                && $request->orderId === 'ord_ghi';
        })
        ->andReturn((function () use ($response) {
            yield $response;
        })());

    $refunds = $order->refunds();

    expect($refunds)->toHaveCount(2);
    expect($refunds->first())->toBe($refund1);
});

it('Order::refunds returns an empty collection when the order has no polar_id', function () {
    $order = Order::factory()->paid()->create(['polar_id' => null]);

    expect($order->refunds())->toHaveCount(0);
});

it('Order::refund throws when the order has no polar_id', function () {
    $order = Order::factory()->paid()->create(['polar_id' => null]);

    expect(fn() => $order->refund(amount: 100))
        ->toThrow(\RuntimeException::class, 'Order has no polar_id');
});

<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Order;
use Illuminate\Support\Facades\Http;

/**
 * Fake the customer-session mint plus the invoice generate/read pair behind it.
 */
function fakeInvoice(?string $url, int $generateStatus = 202): void
{
    Http::fake([
        polarUrl('v1/customer-sessions/') => Http::response(
            polarFixture('CustomerSession', ['token' => 'cst_token']),
            201,
        ),
        polarUrl('v1/customer-portal/orders/order_1/invoice') => Http::sequence()
            ->push([], $generateStatus)
            ->push($url === null ? [] : polarFixture('CustomerOrderInvoice', ['url' => $url])),
    ]);
}

it('returns the invoice URL for an order', function () {
    fakeInvoice('https://polar.sh/invoices/inv_1.pdf');

    $order = Order::factory()->paid()->create([
        'polar_id' => 'order_1',
        'customer_id' => 'cus_1',
    ]);

    expect($order->receiptUrl())->toBe('https://polar.sh/invoices/inv_1.pdf');
});

it('triggers generation before reading the invoice back', function () {
    fakeInvoice('https://polar.sh/invoices/inv_1.pdf');

    $order = Order::factory()->paid()->create(['polar_id' => 'order_1', 'customer_id' => 'cus_1']);
    $order->receiptUrl();

    // Polar generates invoices asynchronously: POST queues the work, GET reads the result.
    $methods = [];
    Http::assertSent(function ($request) use (&$methods) {
        if (str_contains($request->url(), '/invoice')) {
            $methods[] = $request->method();
        }

        return true;
    });

    expect($methods)->toBe(['POST', 'GET']);
});

it('tolerates a 409 from generation, meaning the invoice already exists', function () {
    fakeInvoice('https://polar.sh/invoices/inv_1.pdf', generateStatus: 409);

    $order = Order::factory()->paid()->create(['polar_id' => 'order_1', 'customer_id' => 'cus_1']);

    expect($order->receiptUrl())->toBe('https://polar.sh/invoices/inv_1.pdf');
});

it('returns null while the invoice has no URL yet', function () {
    fakeInvoice(null);

    $order = Order::factory()->paid()->create(['polar_id' => 'order_1', 'customer_id' => 'cus_1']);

    expect($order->receiptUrl())->toBeNull();
});

it('returns null for an order that was never synced', function () {
    $order = Order::factory()->paid()->create(['polar_id' => null, 'customer_id' => 'cus_1']);

    expect($order->receiptUrl())->toBeNull();

    Http::assertNothingSent();
});

it('redirects to the invoice when downloading', function () {
    fakeInvoice('https://polar.sh/invoices/inv_1.pdf');

    $order = Order::factory()->paid()->create(['polar_id' => 'order_1', 'customer_id' => 'cus_1']);

    expect($order->downloadInvoice()->getTargetUrl())->toBe('https://polar.sh/invoices/inv_1.pdf');
});

it('refuses to download an invoice that does not exist', function () {
    $order = Order::factory()->paid()->create(['polar_id' => null, 'customer_id' => 'cus_1']);

    expect(fn() => $order->downloadInvoice())
        ->toThrow(\RuntimeException::class, 'No receipt URL available');
});

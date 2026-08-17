<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

/**
 * Fake the customer-session mint plus a customer-portal endpoint behind it.
 *
 * @param  array<string, mixed>  $portalResponse
 */
function fakeCustomerPortal(string $portalPath, array $portalResponse, int $portalStatus = 200): void
{
    Http::fake([
        polarUrl('v1/customer-sessions/') => Http::response(
            polarFixture('CustomerSession', ['token' => 'cst_token']),
            201,
        ),
        polarUrl($portalPath) => Http::response($portalResponse, $portalStatus),
    ]);
}

/**
 * A billable with a Polar customer already attached.
 */
function billableWithCustomer(string $polarId = 'cus_1'): User
{
    $user = User::factory()->create();
    $user->createAsCustomer(['polar_id' => $polarId]);

    return $user->refresh();
}

it('lists a billable\'s payment methods', function () {
    fakeCustomerPortal('v1/customer-portal/customers/me/payment-methods*', [
        'items' => [
            polarFixture('PaymentMethodCard', ['id' => 'pm_1']),
        ],
        'pagination' => ['total_count' => 1, 'max_page' => 1],
    ]);

    $methods = billableWithCustomer()->paymentMethods();

    expect($methods)->toHaveCount(1)
        ->and($methods->first())->toBeInstanceOf(Data\PaymentMethodCard::class)
        ->and($methods->first()->id)->toBe('pm_1');
});

it('returns an empty collection when the customer has no payment methods', function () {
    fakeCustomerPortal('v1/customer-portal/customers/me/payment-methods*', [
        'items' => [],
        'pagination' => ['total_count' => 0, 'max_page' => 1],
    ]);

    expect(billableWithCustomer()->paymentMethods())->toBeEmpty();
});

it('authenticates payment-method reads with the customer session token', function () {
    fakeCustomerPortal('v1/customer-portal/customers/me/payment-methods*', [
        'items' => [],
        'pagination' => ['total_count' => 0, 'max_page' => 1],
    ]);

    billableWithCustomer()->paymentMethods();

    Http::assertSent(fn($request) => ! str_contains($request->url(), 'customer-portal')
        || $request->hasHeader('Authorization', 'Bearer cst_token'));
});

it('deletes a payment method', function () {
    fakeCustomerPortal('v1/customer-portal/customers/me/payment-methods/pm_1', [], 204);

    billableWithCustomer()->deletePaymentMethod('pm_1');

    Http::assertSent(fn($request) => $request->method() !== 'DELETE'
        || str_ends_with($request->url(), '/v1/customer-portal/customers/me/payment-methods/pm_1'));
});

it('refuses to touch payment methods before the billable has a Polar customer', function () {
    $user = User::factory()->create();

    expect(fn() => $user->paymentMethods())->toThrow(InvalidCustomer::class);
    expect(fn() => $user->deletePaymentMethod('pm_1'))->toThrow(InvalidCustomer::class);

    Http::assertNothingSent();
});

<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

function walletBillable(string $polarId = 'cus_1'): User
{
    $user = User::factory()->create();
    $user->createAsCustomer(['polar_id' => $polarId]);

    return $user->refresh();
}

it('lists the billable wallets through a customer session', function () {
    Http::fake([
        polarUrl('v1/customer-sessions/') => Http::response(polarFixture('CustomerSession', ['token' => 'cst_token']), 201),
        polarUrl('v1/customer-portal/wallets/') => Http::response([
            'items' => [polarFixture('CustomerWallet', ['id' => 'wal_1'])],
            'pagination' => ['total_count' => 1, 'max_page' => 1],
        ], 200),
    ]);

    $wallets = walletBillable()->wallets();

    expect($wallets)->toHaveCount(1)
        ->and($wallets->first())->toBeInstanceOf(Data\CustomerWallet::class)
        ->and($wallets->first()->balance)->toBe(5000);

    Http::assertSent(fn($request) => str_contains($request->url(), '/v1/customer-portal/wallets/')
        && $request->hasHeader('Authorization', 'Bearer cst_token'));
});

it('gets one billable wallet', function () {
    Http::fake([
        polarUrl('v1/customer-sessions/') => Http::response(polarFixture('CustomerSession', ['token' => 'cst_token']), 201),
        polarUrl('v1/customer-portal/wallets/wal_1') => Http::response(polarFixture('CustomerWallet', ['id' => 'wal_1']), 200),
    ]);

    $wallet = walletBillable()->wallet('wal_1');

    expect($wallet->id)->toBe('wal_1');
});

it('lists customer wallets on the facade', function () {
    fakePolarList('v1/customer-portal/wallets/*', [polarFixture('CustomerWallet')]);

    $page = LaravelPolar::listCustomerWallets('cst_token');

    expect($page)->toHaveCount(1);

    Http::assertSent(fn($request) => $request->hasHeader('Authorization', 'Bearer cst_token'));
});

it('walks every wallet page without duplicating the first page', function () {
    Http::fake([
        polarUrl('v1/customer-sessions/') => Http::response(polarFixture('CustomerSession', ['token' => 'cst_token']), 201),
        polarUrl('v1/customer-portal/wallets*') => function ($request) {
            $page = (int) ($request->data()['page'] ?? 1);

            return Http::response([
                'items' => [polarFixture('CustomerWallet', ['id' => 'wal_' . $page])],
                'pagination' => ['total_count' => 2, 'max_page' => 2],
            ]);
        },
    ]);

    $wallets = walletBillable()->wallets();

    expect($wallets)->toHaveCount(2)
        ->and($wallets->pluck('id')->all())->toBe(['wal_1', 'wal_2']);
});

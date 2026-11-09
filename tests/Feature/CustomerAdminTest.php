<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

/**
 * A billable with a Polar customer already attached.
 */
function polarCustomerUser(?string $polarId = 'cus_1'): User
{
    $user = User::factory()->create();
    $user->createAsCustomer(['polar_id' => $polarId]);

    return $user->refresh();
}

it('creates a customer through the facade', function () {
    fakePolar('v1/customers/', polarFixture('CustomerIndividual', ['id' => 'cus_new', 'name' => 'Ada Lovelace']), 201);

    $customer = LaravelPolar::createCustomer([
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
        'external_id' => '42',
        'metadata' => ['plan' => 'pro'],
    ]);

    expect($customer)->toBeInstanceOf(Data\CustomerIndividual::class)
        ->and($customer->id)->toBe('cus_new')
        ->and($customer->name)->toBe('Ada Lovelace');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/v1/customers/')
            && $request['email'] === 'ada@example.com'
            && $request['name'] === 'Ada Lovelace'
            && $request['external_id'] === '42'
            && $request['metadata'] === ['plan' => 'pro'];
    });
});

it('gets a customer by id', function () {
    fakePolar('v1/customers/cus_1', polarFixture('CustomerIndividual', ['id' => 'cus_1']));

    $customer = LaravelPolar::getCustomer('cus_1');

    expect($customer)->toBeInstanceOf(Data\CustomerIndividual::class)
        ->and($customer->id)->toBe('cus_1')
        ->and($customer->type)->toBe('individual');

    Http::assertSent(fn($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/v1/customers/cus_1'));
});

it('gets a customer by external id', function () {
    fakePolar('v1/customers/external/ext_1', polarFixture('CustomerIndividual', ['id' => 'cus_1']));

    $customer = LaravelPolar::getCustomerByExternalId('ext_1');

    expect($customer->id)->toBe('cus_1');

    Http::assertSent(fn($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/v1/customers/external/ext_1'));
});

it('urlencodes customer ids', function () {
    fakePolar('v1/customers/*', polarFixture('CustomerIndividual'));

    LaravelPolar::getCustomer('cus 1');

    Http::assertSent(fn($request) => str_contains($request->url(), 'cus%201'));
});

it('lists customers', function () {
    fakePolarList('v1/customers/*', [
        polarFixture('CustomerIndividual', ['id' => 'cus_1']),
        polarFixture('CustomerIndividual', ['id' => 'cus_2']),
    ]);

    $page = LaravelPolar::listCustomers(['organization_id' => 'org_1']);

    expect($page)->toHaveCount(2)
        ->and($page->first())->toBeInstanceOf(Data\CustomerIndividual::class)
        ->and($page->first()->id)->toBe('cus_1');

    Http::assertSent(fn($request) => str_contains($request->url(), 'organization_id=org_1'));
});

it('updates a customer', function () {
    fakePolar('v1/customers/cus_1', polarFixture('CustomerIndividual', ['id' => 'cus_1', 'name' => 'Grace']));

    $customer = LaravelPolar::updateCustomer('cus_1', ['name' => 'Grace']);

    expect($customer->name)->toBe('Grace');

    Http::assertSent(fn($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/v1/customers/cus_1')
        && $request['name'] === 'Grace');
});

it('deletes a customer', function () {
    fakePolar('v1/customers/cus_1', [], 204);

    LaravelPolar::deleteCustomer('cus_1');

    Http::assertSent(fn($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/v1/customers/cus_1'));
});

it('gets a customer state', function () {
    fakePolar('v1/customers/cus_1/state', polarFixture('CustomerStateIndividual', ['id' => 'cus_1']));

    $state = LaravelPolar::getCustomerState('cus_1');

    expect($state)->toBeInstanceOf(Data\CustomerStateIndividual::class)
        ->and($state->id)->toBe('cus_1')
        ->and($state->activeSubscriptions)->toBe([]);

    Http::assertSent(fn($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/v1/customers/cus_1/state'));
});

it('knows whether a billable has a Polar customer', function (?string $polarId, bool $expected) {
    if ($polarId === null) {
        $user = User::factory()->create();
    } else {
        $user = polarCustomerUser($polarId === 'unset' ? null : $polarId);
    }

    expect($user->hasPolarCustomer())->toBe($expected);
})->with([
    'no customer record' => [null, false],
    'customer without polar_id' => ['unset', false],
    'customer with polar_id' => ['cus_1', true],
]);

it('creates the billable as a Polar customer', function () {
    fakePolar('v1/customers/', polarFixture('CustomerIndividual', ['id' => 'cus_new']), 201);

    $user = User::factory()->create(['name' => 'Ada', 'email' => 'ada@example.com']);

    $customer = $user->createAsPolarCustomer();

    expect($customer)->toBeInstanceOf(Data\CustomerIndividual::class)
        ->and($customer->id)->toBe('cus_new')
        ->and($user->refresh()->customer->polar_id)->toBe('cus_new');

    Http::assertSent(function ($request) use ($user) {
        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/v1/customers/')
            && $request['email'] === 'ada@example.com'
            && $request['name'] === 'Ada'
            && $request['external_id'] === $user->getMorphClass() . ':' . $user->getKey()
            && $request['metadata']['billable_id'] === (string) $user->getKey()
            && $request['metadata']['billable_type'] === $user->getMorphClass();
    });
});

it('merges caller metadata when creating a Polar customer', function () {
    fakePolar('v1/customers/', polarFixture('CustomerIndividual', ['id' => 'cus_new']), 201);

    $user = User::factory()->create();

    $user->createAsPolarCustomer([
        'metadata' => ['plan' => 'pro'],
        'email' => 'billing@example.com',
    ]);

    Http::assertSent(function ($request) use ($user) {
        return $request['metadata']['plan'] === 'pro'
            && $request['metadata']['billable_id'] === (string) $user->getKey()
            && $request['email'] === 'billing@example.com';
    });
});

it('syncs a billable without a Polar customer by creating one', function () {
    fakePolar('v1/customers/external/*', ['detail' => 'not found'], 404);
    fakePolar('v1/customers/', polarFixture('CustomerIndividual', ['id' => 'cus_new']), 201);

    $user = User::factory()->create();

    $customer = $user->syncWithPolar();

    expect($customer->id)->toBe('cus_new')
        ->and($user->hasPolarCustomer())->toBeTrue()
        ->and($user->customer->polar_id)->toBe('cus_new');

    Http::assertSentCount(2);
});

it('reconciles a missing remote customer by external id', function () {
    fakePolar('v1/customers/gone', ['detail' => 'not found'], 404);
    fakePolar('v1/customers/external/*', polarFixture('CustomerIndividual', ['id' => 'cus_9']));

    $user = User::factory()->create();
    Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'gone',
    ]);

    $user->refresh()->syncWithPolar();

    expect($user->customer->polar_id)->toBe('cus_9');
});

it('syncs an existing Polar customer by fetching it', function () {
    fakePolar('v1/customers/cus_1', polarFixture('CustomerIndividual', ['id' => 'cus_1']));

    $user = polarCustomerUser();

    $customer = $user->syncWithPolar();

    expect($customer->id)->toBe('cus_1')
        ->and($user->customer->polar_id)->toBe('cus_1');

    Http::assertSent(fn($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/v1/customers/cus_1'));
    Http::assertSentCount(1);
});

it('gets the billable customer state', function () {
    fakePolar('v1/customers/cus_1/state', polarFixture('CustomerStateIndividual', ['id' => 'cus_1']));

    $state = polarCustomerUser()->customerState();

    expect($state)->toBeInstanceOf(Data\CustomerState::class)
        ->and($state->id)->toBe('cus_1');

    Http::assertSent(fn($request) => str_ends_with($request->url(), '/v1/customers/cus_1/state'));
});

it('refuses to fetch customer state before the billable has a Polar customer', function () {
    expect(fn() => User::factory()->create()->customerState())
        ->toThrow(InvalidCustomer::class);
});

it('keeps a local customer row aligned with the remote id', function () {
    fakePolar('v1/customers/stale_id', polarFixture('CustomerIndividual', ['id' => 'cus_2']));

    $user = User::factory()->create();
    Customer::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'stale_id',
    ]);

    $user->refresh()->syncWithPolar();

    expect($user->refresh()->customer->polar_id)->toBe('cus_2');
});

it('keeps the reserved billable metadata over caller overrides', function () {
    fakePolar('v1/customers/', polarFixture('CustomerIndividual', ['id' => 'cus_new']), 201);

    $user = User::factory()->create();

    $user->createAsPolarCustomer([
        'metadata' => [
            'billable_id' => 'someone-else',
            'billable_type' => 'App\\Models\\Other',
        ],
    ]);

    Http::assertSent(function ($request) use ($user) {
        return $request['metadata']['billable_id'] === (string) $user->getKey()
            && $request['metadata']['billable_type'] === $user->getMorphClass();
    });
});

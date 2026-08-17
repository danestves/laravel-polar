<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Support\Facades\Http;

it('creates a checkout link', function () {
    fakePolar('v1/checkout-links/', polarFixture('CheckoutLink', [
        'id' => 'link_1',
        'url' => 'https://buy.polar.sh/link_1',
    ]), 201);

    $link = LaravelPolar::createCheckoutLink([
        'products' => ['prod_1'],
        'payment_processor' => 'stripe',
    ]);

    expect($link)->toBeInstanceOf(Data\CheckoutLink::class)
        ->and($link->url)->toBe('https://buy.polar.sh/link_1');

    Http::assertSent(fn($request) => $request->method() === 'POST'
        && $request['products'] === ['prod_1']);
});

it('updates a checkout link', function () {
    fakePolar('v1/checkout-links/link_1', polarFixture('CheckoutLink', [
        'id' => 'link_1',
        'label' => 'Renamed',
    ]));

    expect(LaravelPolar::updateCheckoutLink('link_1', ['label' => 'Renamed'])->label)->toBe('Renamed');

    Http::assertSent(fn($request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/v1/checkout-links/link_1'));
});

it('deletes a checkout link', function () {
    fakePolar('v1/checkout-links/link_1', [], 204);

    LaravelPolar::deleteCheckoutLink('link_1');

    Http::assertSent(fn($request) => $request->method() === 'DELETE');
});

it('lists checkout links', function () {
    fakePolarList('v1/checkout-links/*', [
        polarFixture('CheckoutLink', ['id' => 'link_1']),
    ], totalCount: 1);

    $page = LaravelPolar::listCheckoutLinks(['organization_id' => 'org_1']);

    expect($page->first()->id)->toBe('link_1')
        ->and($page->pagination->totalCount)->toBe(1);

    Http::assertSent(fn($request) => str_contains($request->url(), 'organization_id=org_1'));
});

it('gets a checkout link by id', function () {
    fakePolar('v1/checkout-links/link_1', polarFixture('CheckoutLink', ['id' => 'link_1']));

    expect(LaravelPolar::getCheckoutLink('link_1')->id)->toBe('link_1');
});

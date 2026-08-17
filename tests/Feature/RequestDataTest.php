<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Enums\DiscountDuration;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Support\Facades\Http;

it('builds request objects with named arguments and serialises them to Polar field names', function () {
    fakePolar('v1/discounts/', polarFixture('DiscountFixedOnceForeverDuration'), 201);

    LaravelPolar::createDiscount(new Data\DiscountPercentageCreate(
        name: 'Black Friday 50%',
        duration: DiscountDuration::Once,
        basisPoints: 5000,
        organizationId: 'org_xxx',
    ));

    Http::assertSent(fn($request) => $request['name'] === 'Black Friday 50%'
        && $request['duration'] === 'once'
        && $request['basis_points'] === 5000
        && $request['organization_id'] === 'org_xxx');
});

it('accepts the union member wherever the union is expected', function () {
    fakePolar('v1/custom-fields/', polarFixture('CustomFieldText'), 201);

    LaravelPolar::createCustomField(new Data\CustomFieldCreateText(
        slug: 'company',
        name: 'Company name',
        properties: new Data\CustomFieldTextProperties(),
        organizationId: 'org_xxx',
    ));

    Http::assertSent(fn($request) => $request['slug'] === 'company' && $request['type'] === 'text');
});

it('builds a benefit create request with nested properties', function () {
    fakePolar('v1/benefits/', polarFixture('BenefitCustom'), 201);

    LaravelPolar::createBenefit(new Data\BenefitCustomCreate(
        description: 'Premium Support',
        organizationId: 'org_xxx',
        properties: new Data\BenefitCustomCreateProperties(),
    ));

    Http::assertSent(fn($request) => $request['description'] === 'Premium Support'
        && $request['type'] === 'custom');
});

it('builds a checkout link create request', function () {
    fakePolar('v1/checkout-links/', polarFixture('CheckoutLink'), 201);

    LaravelPolar::createCheckoutLink(new Data\CheckoutLinkCreateProduct(
        productId: 'prod_xxx',
        paymentProcessor: 'stripe',
    ));

    Http::assertSent(fn($request) => $request['product_id'] === 'prod_xxx'
        && $request['payment_processor'] === 'stripe');
});

<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Enums\BenefitType;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

it('lists benefits for an organization', function () {
    fakePolarList('v1/benefits/*', [
        polarFixture('BenefitCustom', ['id' => 'ben_1', 'description' => 'Early access']),
    ]);

    $page = (new User())->listBenefits('org_123');

    expect($page->first())->toBeInstanceOf(Data\BenefitCustom::class)
        ->and($page->first()->id)->toBe('ben_1')
        ->and($page->first()->type)->toBe('custom');

    Http::assertSent(fn($request) => str_contains($request->url(), 'organization_id=org_123'));
});

it('gets a benefit by id and morphs it to the right subclass', function () {
    fakePolar('v1/benefits/ben_1', polarFixture('BenefitCustom', ['id' => 'ben_1']));

    $benefit = LaravelPolar::getBenefit('ben_1');

    expect($benefit)->toBeInstanceOf(Data\BenefitCustom::class)
        ->and($benefit->id)->toBe('ben_1');
});

it('lists the grants for a benefit', function () {
    fakePolarList('v1/benefits/ben_1/grants*', []);

    $page = (new User())->listBenefitGrants('ben_1');

    expect($page)->toHaveCount(0);
    Http::assertSent(fn($request) => str_contains($request->url(), '/v1/benefits/ben_1/grants'));
});

it('creates a benefit', function () {
    fakePolar('v1/benefits/', polarFixture('BenefitCustom', ['id' => 'ben_new']), 201);

    $benefit = LaravelPolar::createBenefit([
        'type' => BenefitType::Custom->value,
        'description' => 'Early access',
        'organization_id' => 'org_123',
    ]);

    expect($benefit->id)->toBe('ben_new');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request['description'] === 'Early access'
            && $request['type'] === 'custom';
    });
});

it('updates a benefit', function () {
    fakePolar('v1/benefits/ben_1', polarFixture('BenefitCustom', [
        'id' => 'ben_1',
        'description' => 'Updated',
    ]));

    $benefit = LaravelPolar::updateBenefit('ben_1', ['description' => 'Updated']);

    expect($benefit->description)->toBe('Updated');
    Http::assertSent(fn($request) => $request->method() === 'PATCH');
});

it('deletes a benefit', function () {
    fakePolar('v1/benefits/ben_1', [], 204);

    LaravelPolar::deleteBenefit('ben_1');

    Http::assertSent(fn($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/v1/benefits/ben_1'));
});

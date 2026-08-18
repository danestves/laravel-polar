<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Support\Facades\Http;

it('fetches metrics for a period', function () {
    fakePolar('v1/metrics/*', polarFixture('MetricsResponse'));

    $metrics = LaravelPolar::getMetrics([
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'interval' => 'day',
    ]);

    expect($metrics)->toBeInstanceOf(Data\MetricsResponse::class);

    Http::assertSent(function ($request) {
        $url = urldecode($request->url());

        return str_contains($url, 'start_date=2026-01-01')
            && str_contains($url, 'end_date=2026-01-31')
            && str_contains($url, 'interval=day');
    });
});

it('lists files', function () {
    fakePolarList('v1/files/*', [polarFixture('DownloadableFileRead', ['id' => 'file_1'])]);

    $page = LaravelPolar::listFiles(['organization_id' => 'org_1']);

    expect($page->first())->toBeInstanceOf(Data\DownloadableFileRead::class)
        ->and($page->first()->id)->toBe('file_1');

    Http::assertSent(fn($request) => str_contains($request->url(), 'organization_id=org_1'));
});

it('lists organizations', function () {
    fakePolarList('v1/organizations/*', [
        polarFixture('Organization', ['id' => 'org_1', 'slug' => 'acme']),
    ]);

    expect(LaravelPolar::listOrganizations(['slug' => 'acme'])->first()->slug)->toBe('acme');

    Http::assertSent(fn($request) => str_contains($request->url(), 'slug=acme'));
});

it('gets an organization by id', function () {
    fakePolar('v1/organizations/org_1', polarFixture('Organization', [
        'id' => 'org_1',
        'name' => 'Acme',
    ]));

    $organization = LaravelPolar::getOrganization('org_1');

    expect($organization)->toBeInstanceOf(Data\Organization::class)
        ->and($organization->name)->toBe('Acme');
});

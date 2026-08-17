<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('lists license keys', function () {
    fakePolarList('v1/license-keys/*', [polarFixture('LicenseKeyRead', ['id' => 'lk_1'])]);

    expect(LaravelPolar::listLicenseKeys(['organization_id' => 'org_1'])->first()->id)->toBe('lk_1');

    Http::assertSent(fn($request) => str_contains($request->url(), 'organization_id=org_1'));
});

it('gets a license key with its activations', function () {
    fakePolar('v1/license-keys/lk_1', polarFixture('LicenseKeyWithActivations', ['id' => 'lk_1']));

    expect(LaravelPolar::getLicenseKey('lk_1'))
        ->toBeInstanceOf(Data\LicenseKeyWithActivations::class);
});

it('updates a license key', function () {
    fakePolar('v1/license-keys/lk_1', polarFixture('LicenseKeyRead', [
        'id' => 'lk_1',
        'usage' => 5,
    ]));

    expect(LaravelPolar::updateLicenseKey('lk_1', ['usage' => 5])->usage)->toBe(5);

    Http::assertSent(fn($request) => $request->method() === 'PATCH');
});

it('validates a license key against the public customer-portal route', function () {
    fakePolar('v1/customer-portal/license-keys/validate', polarFixture('ValidatedLicenseKey', [
        'id' => 'lk_1',
    ]));

    $validated = LaravelPolar::validateLicenseKey('KEY-123', organizationId: 'org_explicit');

    expect($validated)->toBeInstanceOf(Data\ValidatedLicenseKey::class);

    Http::assertSent(fn($request) => $request['key'] === 'KEY-123'
        && $request['organization_id'] === 'org_explicit'
        && str_ends_with($request->url(), '/v1/customer-portal/license-keys/validate'));
});

it('falls back to the configured organization id', function () {
    Config::set('polar.organization_id', 'org_from_config');
    fakePolar('v1/customer-portal/license-keys/validate', polarFixture('ValidatedLicenseKey'));

    LaravelPolar::validateLicenseKey('KEY-123');

    Http::assertSent(fn($request) => $request['organization_id'] === 'org_from_config');
});

it('refuses to validate without an organization id', function () {
    Config::set('polar.organization_id', null);

    expect(fn() => LaravelPolar::validateLicenseKey('KEY-123'))
        ->toThrow(\InvalidArgumentException::class, 'organization id is required');
});

it('activates a license key with a label and metadata', function () {
    Config::set('polar.organization_id', 'org_1');
    fakePolar('v1/customer-portal/license-keys/activate', polarFixture('LicenseKeyActivationRead', [
        'id' => 'act_1',
    ]));

    $activation = LaravelPolar::activateLicenseKey('KEY-123', 'Laptop', meta: ['host' => 'macbook']);

    expect($activation->id)->toBe('act_1');

    Http::assertSent(fn($request) => $request['key'] === 'KEY-123'
        && $request['label'] === 'Laptop'
        && $request['meta'] === ['host' => 'macbook']);
});

it('deactivates a license key activation', function () {
    Config::set('polar.organization_id', 'org_1');
    fakePolar('v1/customer-portal/license-keys/deactivate', [], 204);

    LaravelPolar::deactivateLicenseKey('KEY-123', 'act_1');

    Http::assertSent(fn($request) => $request['activation_id'] === 'act_1'
        && str_ends_with($request->url(), '/v1/customer-portal/license-keys/deactivate'));
});

it('lists a billable\'s license keys through a customer session', function () {
    Http::fake([
        polarUrl('v1/customer-sessions/') => Http::response(
            polarFixture('CustomerSession', ['token' => 'cst_token']),
            201,
        ),
        polarUrl('v1/customer-portal/license-keys/*') => Http::response([
            'items' => [polarFixture('LicenseKeyRead', ['id' => 'lk_1'])],
            'pagination' => ['total_count' => 1, 'max_page' => 1],
        ]),
    ]);

    $user = User::factory()->create();
    $user->createAsCustomer(['polar_id' => 'cus_1']);

    expect($user->licenseKeys()->pluck('id')->all())->toBe(['lk_1']);

    // The portal call must authenticate as the customer, not with the org token.
    Http::assertSent(fn($request) => ! str_contains($request->url(), 'customer-portal')
        || $request->hasHeader('Authorization', 'Bearer cst_token'));
});

it('refuses to list license keys before the billable has a Polar customer', function () {
    $user = User::factory()->create();

    expect(fn() => $user->licenseKeys())->toThrow(InvalidCustomer::class);

    Http::assertNothingSent();
});

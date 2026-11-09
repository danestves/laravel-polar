<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Support\Facades\Http;

it('lists disputes', function () {
    fakePolarList('v1/disputes/*', [
        polarFixture('Dispute', ['id' => 'dsp_1']),
        polarFixture('Dispute', ['id' => 'dsp_2']),
    ]);

    $disputes = LaravelPolar::listDisputes(['status' => 'needs_response']);

    expect($disputes)->toHaveCount(2)
        ->and($disputes->first())->toBeInstanceOf(Data\Dispute::class);

    Http::assertSent(fn($request) => str_contains($request->url(), '/v1/disputes/')
        && str_contains($request->url(), 'status=needs_response'));
});

it('gets a dispute', function () {
    fakePolar('v1/disputes/dsp_1', polarFixture('Dispute', ['id' => 'dsp_1']));

    $dispute = LaravelPolar::getDispute('dsp_1');

    expect($dispute->id)->toBe('dsp_1')
        ->and($dispute->amount)->toBe(1000)
        ->and($dispute->orderId)->toBe('00000000-0000-0000-0000-000000000000')
        ->and($dispute->customer->email)->toBeNull()
        ->and($dispute->customer)->toBeInstanceOf(Data\DisputeCustomer::class);
});

it('accepts a dispute', function () {
    fakePolar('v1/disputes/dsp_1/accept', polarFixture('Dispute', ['id' => 'dsp_1', 'status' => 'prevented']));

    LaravelPolar::acceptDispute('dsp_1');

    Http::assertSent(fn($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/v1/disputes/dsp_1/accept'));
});

it('hydrates a dispute customer email when Polar includes it', function () {
    $data = polarFixture('Dispute', ['id' => 'dsp_email']);
    $data['customer']['email'] = 'jane@example.com';

    fakePolar('v1/disputes/dsp_email', $data);

    expect(LaravelPolar::getDispute('dsp_email')->customer->email)->toBe('jane@example.com');
});

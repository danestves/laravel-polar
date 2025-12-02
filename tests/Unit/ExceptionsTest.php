<?php

use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\Exceptions\InvalidMetadataPayload;
use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Danestves\LaravelPolar\Exceptions\ReservedMetadataKeys;
use Danestves\LaravelPolar\Tests\Fixtures\User;

it('can create InvalidCustomer exception', function () {
    $user = User::factory()->create();
    $exception = InvalidCustomer::notYetCreated($user);

    expect($exception)->toBeInstanceOf(InvalidCustomer::class);
    expect($exception->getMessage())->toContain('is not a Polar customer yet');
});

it('can create ReservedMetadataKeys exception', function () {
    $exception = ReservedMetadataKeys::overwriteAttempt();

    expect($exception)->toBeInstanceOf(ReservedMetadataKeys::class);
    expect($exception->getMessage())->toContain('billable_id');
    expect($exception->getMessage())->toContain('billable_type');
    expect($exception->getMessage())->toContain('subscription_type');
});

it('can create InvalidMetadataPayload exception', function () {
    $exception = new InvalidMetadataPayload();

    expect($exception)->toBeInstanceOf(InvalidMetadataPayload::class);
});

it('can create PolarApiError exception', function () {
    $exception = new PolarApiError('Test error message');

    expect($exception)->toBeInstanceOf(PolarApiError::class);
    expect($exception->getMessage())->toBe('Test error message');
});

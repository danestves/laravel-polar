<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Data\Sessions\CustomerSessionCustomerIDCreateData;
use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\RedirectResponse;

trait ManagesCustomer // @phpstan-ignore-line trait.unused - ManagesCustomer is used in Billable trait
{
    /**
     * Create a customer record for the billable model.
     *
     * @param  array<string, string|int>  $attributes
     */
    public function createAsCustomer(array $attributes = []): Customer
    {
        return $this->customer()->create($attributes);
    }

    /**
     * Get the customer related to the billable model.
     *
     * @return MorphOne<Customer, covariant $this>
     */
    public function customer(): MorphOne
    {
        return $this->morphOne(LaravelPolar::$customerModel, 'billable');
    }

    /**
     * Get the billable's name to associate with Polar.
     */
    public function polarName(): ?string
    {
        return $this->name ?? null;
    }

    /**
     * Get the billable's email address to associate with Polar.
     */
    public function polarEmail(): ?string
    {
        return $this->email ?? null;
    }

    /**
     * Generate a redirect response to the billable's customer portal.
     */
    public function redirectToCustomerPortal(): RedirectResponse
    {
        return new RedirectResponse($this->customerPortalUrl());
    }

    /**
     * Get the customer portal url for this billable.
     *
     * @throws PolarApiError
     * @throws InvalidCustomer
     */
    public function customerPortalUrl(): string
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            throw InvalidCustomer::notYetCreated($this);
        }

        $response = LaravelPolar::createCustomerSession(new CustomerSessionCustomerIDCreateData(
            customerId: $this->customer->polar_id,
        ));

        if (!$response) {
            throw new PolarApiError('Failed to create customer session');
        }

        return $response->customerPortalUrl;
    }
}

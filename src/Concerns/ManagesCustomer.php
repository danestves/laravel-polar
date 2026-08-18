<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Danestves\LaravelPolar\Data;
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
     *
     * Returns an Inertia::location response when invoked from an Inertia
     * request (detected via the X-Inertia header). This avoids CORS errors
     * when Inertia's XHR-based client tries to follow a redirect to an
     * external host. Falls back to a standard RedirectResponse otherwise.
     */
    public function redirectToCustomerPortal(): \Symfony\Component\HttpFoundation\Response
    {
        $url = $this->customerPortalUrl();

        $request = request();
        if ($request->header('X-Inertia') === 'true' && class_exists(\Inertia\Inertia::class)) {
            return \Inertia\Inertia::location($url);
        }

        return new RedirectResponse($url);
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

        return LaravelPolar::createCustomerSession(
            new Data\CustomerSessionCustomerIDCreate(customerId: $this->customer->polar_id),
        )->customerPortalUrl;
    }
}

<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\Exceptions\NotFoundException;
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
     * Determine if the billable already has a Polar customer.
     */
    public function hasPolarCustomer(): bool
    {
        return $this->customer !== null && $this->customer->polar_id !== null;
    }

    /**
     * Create the billable as a customer in Polar and store its id locally.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws PolarApiError
     */
    public function createAsPolarCustomer(array $attributes = []): Data\Customer
    {
        $metadata = $attributes['metadata'] ?? [];
        unset($attributes['metadata']);

        $metadata = [
            ...(is_array($metadata) ? $metadata : []),
            ...$this->polarReservedMetadata(),
        ];

        $created = LaravelPolar::createCustomer([
            'email' => $this->polarEmail(),
            'name' => $this->polarName(),
            'external_id' => $this->polarExternalId(),
            'metadata' => $metadata,
            ...$attributes,
        ]);

        $local = $this->customer()->updateOrCreate([], ['polar_id' => $created->id]);
        $this->setRelation('customer', $local);

        return $created;
    }

    /**
     * Create the billable in Polar if needed, then return its remote customer.
     *
     * Reconciles by the stored id first, then by external id, and finally creates the
     * customer. The local relation is updated in place so callers do not need a refresh.
     *
     * @throws PolarApiError
     */
    public function syncWithPolar(): Data\Customer
    {
        if ($this->customer !== null && $this->customer->polar_id !== null) {
            try {
                return $this->rememberPolarCustomer(
                    LaravelPolar::getCustomer($this->customer->polar_id),
                );
            } catch (NotFoundException) {
                // The remote customer was deleted; fall through and reconcile by external id.
            }
        }

        try {
            $customer = LaravelPolar::getCustomerByExternalId($this->polarExternalId());
        } catch (NotFoundException) {
            return $this->createAsPolarCustomer();
        }

        return $this->rememberPolarCustomer($customer);
    }

    /**
     * The stable id this billable uses in Polar's external-id field.
     *
     * The morph class is included because Polar requires external ids to be unique across the
     * organization, while primary keys are only unique within one table.
     */
    public function polarExternalId(): string
    {
        return $this->getMorphClass() . ':' . $this->getKey();
    }

    /**
     * Metadata keys the package uses to find this billable from a webhook.
     *
     * @return array{billable_id: string, billable_type: string}
     */
    public function polarReservedMetadata(): array
    {
        return [
            'billable_id' => (string) $this->getKey(),
            'billable_type' => $this->getMorphClass(),
        ];
    }

    /**
     * Store the remote customer id locally and refresh the cached relation.
     */
    private function rememberPolarCustomer(Data\Customer $customer): Data\Customer
    {
        $local = $this->customer()->updateOrCreate([], ['polar_id' => $customer->id]);
        $this->setRelation('customer', $local);

        return $customer;
    }

    /**
     * Get the billable's state from Polar.
     *
     * @throws PolarApiError
     * @throws InvalidCustomer
     */
    public function customerState(): Data\CustomerState
    {
        if (! $this->hasPolarCustomer()) {
            throw InvalidCustomer::notYetCreated($this);
        }

        /** @var Customer $localCustomer */
        $localCustomer = $this->customer;

        return LaravelPolar::getCustomerState($localCustomer->polar_id);
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

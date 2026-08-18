<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Data;
use Illuminate\Support\Collection;

trait ManagesPaymentMethods // @phpstan-ignore-line trait.unused - ManagesPaymentMethods is used in Billable trait
{
    /**
     * List the billable's saved payment methods.
     *
     * Mints a short-lived customer session under the hood, so this works
     * without sharing the org-scoped admin token with the client.
     *
     * @return Collection<int, Data\CustomerPaymentMethod>
     *
     * @throws InvalidCustomer
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function paymentMethods(): Collection
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            throw InvalidCustomer::notYetCreated($this);
        }

        $session = LaravelPolar::createCustomerSession(
            new Data\CustomerSessionCustomerIDCreate(customerId: $this->customer->polar_id),
        );

        return LaravelPolar::listCustomerPaymentMethods($session->token)->collect();
    }

    /**
     * Delete one of the billable's saved payment methods.
     *
     * @throws InvalidCustomer
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function deletePaymentMethod(string $paymentMethodId): void
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            throw InvalidCustomer::notYetCreated($this);
        }

        $session = LaravelPolar::createCustomerSession(
            new Data\CustomerSessionCustomerIDCreate(customerId: $this->customer->polar_id),
        );

        LaravelPolar::deleteCustomerPaymentMethod($session->token, $paymentMethodId);
    }
}

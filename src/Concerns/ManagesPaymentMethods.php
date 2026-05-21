<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Support\Collection;
use Polar\Models\Components;
use Polar\Models\Errors;
use Polar\Models\Operations;

trait ManagesPaymentMethods // @phpstan-ignore-line trait.unused - ManagesPaymentMethods is used in Billable trait
{
    /**
     * List the billable's saved payment methods.
     *
     * Mints a short-lived customer session under the hood, so this works
     * without sharing the org-scoped admin token with the client.
     *
     * @return Collection<int, Components\PaymentMethodCard|Components\PaymentMethodGeneric>
     *
     * @throws InvalidCustomer
     * @throws Errors\APIException
     * @throws \Exception
     */
    public function paymentMethods(): Collection
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            throw InvalidCustomer::notYetCreated($this);
        }

        $session = LaravelPolar::createCustomerSession(
            new Components\CustomerSessionCustomerIDCreate(customerId: $this->customer->polar_id),
        );

        $security = new Operations\CustomerPortalCustomersListPaymentMethodsSecurity(customerSession: $session->token);

        $generator = LaravelPolar::sdk()->customerPortal->customers->listPaymentMethods(security: $security);

        foreach ($generator as $response) {
            if ($response->statusCode === 200) {
                return collect($response->listResourceCustomerPaymentMethod->items ?? []);
            }
        }

        return collect();
    }

    /**
     * Delete one of the billable's saved payment methods.
     *
     * @throws InvalidCustomer
     * @throws Errors\APIException
     * @throws \Exception
     */
    public function deletePaymentMethod(string $paymentMethodId): void
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            throw InvalidCustomer::notYetCreated($this);
        }

        $session = LaravelPolar::createCustomerSession(
            new Components\CustomerSessionCustomerIDCreate(customerId: $this->customer->polar_id),
        );

        $security = new Operations\CustomerPortalCustomersDeletePaymentMethodSecurity(customerSession: $session->token);

        $response = LaravelPolar::sdk()->customerPortal->customers->deletePaymentMethod(
            security: $security,
            id: $paymentMethodId,
        );

        if ($response->statusCode !== 200 && $response->statusCode !== 204) {
            throw new Errors\APIException('Failed to delete payment method', $response->statusCode, '', null);
        }
    }
}

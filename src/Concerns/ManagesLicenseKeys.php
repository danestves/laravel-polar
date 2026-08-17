<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Data;
use Illuminate\Support\Collection;

trait ManagesLicenseKeys // @phpstan-ignore-line trait.unused - ManagesLicenseKeys is used in Billable trait
{
    /**
     * List the license keys the billable owns, scoped to an optional benefit.
     *
     * @return Collection<int, Data\LicenseKeyRead>
     *
     * @throws InvalidCustomer
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function licenseKeys(?string $benefitId = null): Collection
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            throw InvalidCustomer::notYetCreated($this);
        }

        $session = LaravelPolar::createCustomerSession(
            new Data\CustomerSessionCustomerIDCreate(customerId: $this->customer->polar_id),
        );

        return LaravelPolar::listCustomerLicenseKeys(
            $session->token,
            ['benefit_id' => $benefitId],
        )->collect();
    }
}

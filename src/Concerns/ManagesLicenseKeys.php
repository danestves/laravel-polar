<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Support\Collection;
use Polar\Models\Components;
use Polar\Models\Errors;
use Polar\Models\Operations;

trait ManagesLicenseKeys // @phpstan-ignore-line trait.unused - ManagesLicenseKeys is used in Billable trait
{
    /**
     * List the license keys the billable owns, scoped to an optional benefit.
     *
     * @return Collection<int, Components\LicenseKeyRead>
     *
     * @throws InvalidCustomer
     * @throws Errors\APIException
     * @throws \Exception
     */
    public function licenseKeys(?string $benefitId = null): Collection
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            throw InvalidCustomer::notYetCreated($this);
        }

        $session = LaravelPolar::createCustomerSession(
            new Components\CustomerSessionCustomerIDCreate(customerId: $this->customer->polar_id),
        );

        $security = new Operations\CustomerPortalLicenseKeysListSecurity(customerSession: $session->token);

        $generator = LaravelPolar::sdk()->customerPortal->licenseKeys->list(
            security: $security,
            benefitId: $benefitId,
        );

        foreach ($generator as $response) {
            if ($response->statusCode === 200) {
                return collect($response->listResourceLicenseKeyRead->items ?? []);
            }
        }

        return collect();
    }
}

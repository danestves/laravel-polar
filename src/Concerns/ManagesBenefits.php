<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Http\Page;
use Danestves\LaravelPolar\LaravelPolar;

trait ManagesBenefits // @phpstan-ignore-line trait.unused - ManagesBenefits is used in Billable trait
{
    /**
     * List all benefits for an organization.
     *
     * @return Page<Data\Benefit>
     *
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function listBenefits(string $organizationId): Page
    {
        return LaravelPolar::listBenefits(['organization_id' => $organizationId]);
    }

    /**
     * Get a specific benefit by ID.
     *
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function getBenefit(string $benefitId): Data\Benefit
    {
        return LaravelPolar::getBenefit($benefitId);
    }

    /**
     * List all grants for a specific benefit.
     *
     * @return Page<Data\BenefitGrant>
     *
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function listBenefitGrants(string $benefitId): Page
    {
        return LaravelPolar::listBenefitGrants($benefitId);
    }
}

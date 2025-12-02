<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;
use Polar\Models\Operations;

trait ManagesBenefits // @phpstan-ignore-line trait.unused - ManagesBenefits is used in Billable trait
{
    /**
     * List all benefits for an organization.
     *
     * @throws \Polar\Models\Errors\APIException
     * @throws \Exception
     */
    public function listBenefits(string $organizationId): Operations\BenefitsListResponse
    {
        $request = new Operations\BenefitsListRequest(
            organizationId: $organizationId,
        );

        return LaravelPolar::listBenefits($request);
    }

    /**
     * Get a specific benefit by ID.
     *
     * @throws \Polar\Models\Errors\APIException
     * @throws \Exception
     */
    public function getBenefit(string $benefitId): Components\BenefitCustom|Components\BenefitDiscord|Components\BenefitGitHubRepository|Components\BenefitDownloadables|Components\BenefitLicenseKeys|Components\BenefitMeterCredit
    {
        return LaravelPolar::getBenefit($benefitId);
    }

    /**
     * List all grants for a specific benefit.
     *
     * @throws \Polar\Models\Errors\APIException
     * @throws \Exception
     */
    public function listBenefitGrants(string $benefitId): Operations\BenefitsGrantsResponse
    {
        $request = new Operations\BenefitsGrantsRequest(
            id: $benefitId,
        );

        return LaravelPolar::listBenefitGrants($request);
    }
}

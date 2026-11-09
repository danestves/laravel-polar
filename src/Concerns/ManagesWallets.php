<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Exceptions\InvalidCustomer;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Support\Collection;

trait ManagesWallets // @phpstan-ignore-line trait.unused - ManagesWallets is used in the Billable trait
{
    /**
     * List the billable's wallets.
     *
     * Mints a short-lived customer session so the org-scoped admin token never reaches the
     * client.
     *
     * @return Collection<int, Data\CustomerWallet>
     *
     * @throws InvalidCustomer
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function wallets(): Collection
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            throw InvalidCustomer::notYetCreated($this);
        }

        $session = LaravelPolar::createCustomerSession(
            new Data\CustomerSessionCustomerIDCreate(customerId: $this->customer->polar_id),
        );

        $page = LaravelPolar::listCustomerWallets($session->token);
        $wallets = $page->collect();

        for ($pageNumber = 2; $pageNumber <= $page->pagination->maxPage; $pageNumber++) {
            $wallets = $wallets->merge(
                LaravelPolar::listCustomerWallets($session->token, ['page' => $pageNumber])->collect(),
            );
        }

        return $wallets;
    }

    /**
     * Get one of the billable's wallets.
     *
     * @throws InvalidCustomer
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function wallet(string $walletId): Data\CustomerWallet
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            throw InvalidCustomer::notYetCreated($this);
        }

        $session = LaravelPolar::createCustomerSession(
            new Data\CustomerSessionCustomerIDCreate(customerId: $this->customer->polar_id),
        );

        return LaravelPolar::getCustomerWallet($session->token, $walletId);
    }
}

<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Subscription;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait ManagesSubscription // @phpstan-ignore-line trait.unused - ManagesSubscription is used in Billable trait
{
    /**
     * Get all of the subscriptions for the billable.
     *
     * @return MorphMany<Subscription, covariant $this>
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(LaravelPolar::$subscriptionModel, 'billable')->orderByDesc('created_at');
    }

    /**
     * Get a subscription instance by type.
     */
    public function subscription(string $type = 'default'): ?Subscription
    {
        return $this->subscriptions->where('type', $type)->first();
    }

    /**
     * Determine if the billable has a valid subscription.
     */
    public function subscribed(string $type = 'default', ?string $productId = null): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return $productId !== null && $productId !== '' && $productId !== '0' ? $subscription->hasProduct($productId) : true;
    }

    /**
     * Determine if the billable has a valid subscription for the given variant.
     */
    public function subscribedToProduct(string $productId, string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return $subscription->hasProduct($productId);
    }
}

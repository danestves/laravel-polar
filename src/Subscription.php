<?php

namespace Danestves\LaravelPolar;

use Danestves\LaravelPolar\Database\Factories\SubscriptionFactory;
use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Danestves\LaravelPolar\Enums\CustomerCancellationReason;
use Danestves\LaravelPolar\Enums\SubscriptionProrationBehavior;
use Danestves\LaravelPolar\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $billable_type
 * @property int $billable_id
 * @property string $type
 * @property string $polar_id
 * @property SubscriptionStatus $status
 * @property string $product_id
 * @property string|null $discount_id
 * @property \Carbon\CarbonInterface|null $current_period_start
 * @property \Carbon\CarbonInterface|null $current_period_end
 * @property \Carbon\CarbonInterface|null $trial_ends_at
 * @property bool $cancel_at_period_end
 * @property \Carbon\CarbonInterface|null $canceled_at
 * @property \Carbon\CarbonInterface|null $ends_at
 * @property bool $pause_at_period_end
 * @property \Carbon\CarbonInterface|null $paused_at
 * @property \Carbon\CarbonInterface|null $resumes_at
 * @property \Carbon\CarbonInterface|null $started_at
 * @property \Carbon\CarbonInterface|null $past_due_at
 * @property int|null $polar_updated_at_us
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property \Danestves\LaravelPolar\Billable $billable
 *
 * @mixin \Eloquent
 */
class Subscription extends Model // @phpstan-ignore-line propertyTag.trait - Billable is used in the user final code
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'polar_subscriptions';

    /**
    * The attributes that are not mass assignable.
    *
    * @var array<string>
    */
    protected $guarded = [];

    /**
     * Get the billable model related to the subscription.
     *
     * @return MorphTo<Model, covariant $this>
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Determine if the subscription is active, on trial, past due, or within its grace period.
     */
    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->pastDue() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is incomplete.
     */
    public function incomplete(): bool
    {
        return $this->status === SubscriptionStatus::Incomplete;
    }

    /**
     * Filter query by incomplete.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeIncomplete(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::Incomplete);
    }

    /**
     * Determine if the subscription is incomplete expired.
     */
    public function incompleteExpired(): bool
    {
        return $this->status === SubscriptionStatus::IncompleteExpired;
    }

    /**
     * Filter query by incomplete expired.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeIncompleteExpired(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::IncompleteExpired);
    }

    /**
     * Determine if the subscription is trialing.
     */
    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing;
    }

    /**
     * Filter query by on trial.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeOnTrial(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::Trialing);
    }

    /**
     * Get the trial end date.
     */
    public function trialEndsAt(): ?\Carbon\CarbonInterface
    {
        return $this->trial_ends_at;
    }

    /**
     * Determine if the subscription's trial has expired.
     */
    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Update the trial end date for the subscription.
     */
    public function updateTrial(\DateTimeInterface $trialEnd): self
    {
        return $this->updateAndSync(new Data\SubscriptionUpdateBase(
            trialEnd: CarbonImmutable::instance($trialEnd),
        ));
    }

    /**
     * Check if the subscription is active.
     */
    public function active(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    /**
     * Filter query by active.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::Active);
    }

    /**
     * Check if the subscription is past due.
     */
    public function pastDue(): bool
    {
        return $this->status === SubscriptionStatus::PastDue;
    }

    /**
     * Filter query by past due.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopePastDue(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::PastDue);
    }

    /**
     * Check if the subscription is unpaid.
     */
    public function unpaid(): bool
    {
        return $this->status === SubscriptionStatus::Unpaid;
    }

    /**
     * Filter query by unpaid.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeUnpaid(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::Unpaid);
    }

    /**
     * Check if the subscription is cancelled.
     */
    public function cancelled(): bool
    {
        return $this->status === SubscriptionStatus::Canceled;
    }

    /**
     * Filter query by cancelled.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeCancelled(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::Canceled);
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     */
    public function onGracePeriod(): bool
    {
        return $this->cancelled() && $this->ends_at?->isFuture();
    }

    /**
     * Determine if the subscription is paused.
     */
    public function paused(): bool
    {
        return $this->status === SubscriptionStatus::Paused;
    }

    /**
     * Filter query by paused.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopePaused(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::Paused);
    }

    /**
     * Determine if the subscription is scheduled to pause at the end of the period.
     */
    public function onPauseGracePeriod(): bool
    {
        return $this->pause_at_period_end && $this->current_period_end?->isFuture();
    }

    /**
     * Determine if the subscription is on a specific product.
     */
    public function hasProduct(string $productId): bool
    {
        return $this->product_id === $productId;
    }

    /**
     * Swap the subscription to a new product.
     */
    public function swap(string $productId, ?SubscriptionProrationBehavior $prorationBehavior = SubscriptionProrationBehavior::Prorate): self
    {
        return $this->updateAndSync(new Data\SubscriptionUpdateBase(
            productId: $productId,
            prorationBehavior: $prorationBehavior ?? SubscriptionProrationBehavior::Prorate,
        ));
    }

    /**
     * Swap the subscription to a new product plan and invoice immediately.
     */
    public function swapAndInvoice(string $productId): self
    {
        return $this->swap($productId, SubscriptionProrationBehavior::Invoice);
    }

    /**
     * Cancel the subscription at the end of the current period.
     */
    public function cancel(): self
    {
        return $this->updateAndSync(new Data\SubscriptionCancel(cancelAtPeriodEnd: true));
    }

    /**
     * Cancel and revoke the subscription immediately.
     */
    public function cancelNow(
        ?CustomerCancellationReason $reason = null,
        ?string $comment = null,
    ): self {
        return $this->revoke($reason, $comment);
    }

    /**
     * Revoke the subscription immediately, ending billing and access now.
     */
    public function revoke(
        ?CustomerCancellationReason $reason = null,
        ?string $comment = null,
    ): self {
        return $this->updateAndSync(new Data\SubscriptionRevoke(
            customerCancellationReason: $reason,
            customerCancellationComment: $comment,
        ));
    }

    /**
     * Resume a subscription that was cancelled at the end of the period.
     */
    public function resume(): self
    {
        if ($this->status === SubscriptionStatus::IncompleteExpired) {
            throw new PolarApiError('Subscription is incomplete and expired.');
        }

        return $this->updateAndSync(new Data\SubscriptionCancel(cancelAtPeriodEnd: false));
    }

    /**
     * Resume a subscription that has already been paused.
     *
     * This starts a new billing period and charges the customer immediately, unlike
     * {@see resume()}, which only undoes a scheduled cancellation.
     */
    public function resumePaused(): self
    {
        return $this->updateAndSync(new Data\SubscriptionResume());
    }

    /**
     * Schedule a pause at the end of the current period, or cancel a scheduled pause
     * when the subscription has not paused yet.
     */
    public function pause(?\DateTimeInterface $resumesAt = null): self
    {
        $resumesAt = $resumesAt === null
            ? null
            : ($resumesAt instanceof CarbonImmutable ? $resumesAt : CarbonImmutable::instance($resumesAt));

        return $this->updateAndSync(new Data\SubscriptionPause(
            pauseAtPeriodEnd: true,
            resumesAt: $resumesAt,
        ));
    }

    /**
     * Cancel a scheduled pause before it takes effect.
     */
    public function unpause(): self
    {
        return $this->updateAndSync(new Data\SubscriptionPause(pauseAtPeriodEnd: false));
    }

    /**
     * Apply a discount to this subscription on the next billing cycle.
     */
    public function applyDiscount(string $discountId): self
    {
        return $this->updateAndSync(new Data\SubscriptionUpdateBase(discountId: $discountId));
    }

    /**
     * List the seats on this subscription.
     */
    public function seats(): Data\SeatsList
    {
        return LaravelPolar::listSeats(subscriptionId: $this->polar_id);
    }

    /**
     * Assign a seat on this subscription to a member by email, customer id, or
     * external customer id. Passing none of the three creates a pending seat
     * that can be claimed via an invitation link.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function assignSeat(?string $email = null, ?string $customerId = null, ?string $externalCustomerId = null, ?array $metadata = null, ?bool $immediateClaim = false): Data\CustomerSeat
    {
        return LaravelPolar::assignSeat(new Data\SeatAssign(
            subscriptionId: $this->polar_id,
            email: $email,
            customerId: $customerId,
            externalCustomerId: $externalCustomerId,
            metadata: $metadata,
            immediateClaim: $immediateClaim,
        ));
    }

    /**
     * Revoke a seat from this subscription.
     */
    public function revokeSeat(string $seatId): Data\CustomerSeat
    {
        return LaravelPolar::revokeSeat($seatId);
    }

    /**
     * Resend the invitation email for a pending seat on this subscription.
     */
    public function resendSeatInvitation(string $seatId): Data\CustomerSeat
    {
        return LaravelPolar::resendSeatInvitation($seatId);
    }

    /**
     * Remove any active discount from this subscription on the next billing cycle.
     */
    public function removeDiscount(): self
    {
        // Polar reads an absent key as "leave alone" and an explicit null as "clear", so this
        // one update has to keep its nulls.
        return $this->updateAndSync(new Data\SubscriptionUpdateBase(discountId: null), keepNulls: true);
    }

    /**
     * Update the subscription on Polar and sync the local record from the response.
     */
    private function updateAndSync(Data\SubscriptionUpdate $request, bool $keepNulls = false): self
    {
        $this->syncFromResource(LaravelPolar::updateSubscription(
            subscriptionId: $this->polar_id,
            request: $request,
            keepNulls: $keepNulls,
        ));

        return $this;
    }

    /**
     * Sync the local record from a Polar subscription resource.
     */
    private function syncFromResource(Data\Subscription $subscription): self
    {
        if ($this->isStale($this->watermarkMicros($subscription->modifiedAt))) {
            return $this;
        }

        $values = [
            'status' => $subscription->status,
            'product_id' => $subscription->productId,
            'discount_id' => $subscription->discountId,
            'current_period_start' => Carbon::make($subscription->currentPeriodStart),
            'current_period_end' => Carbon::make($subscription->currentPeriodEnd),
            'trial_ends_at' => $subscription->trialEnd !== null ? Carbon::make($subscription->trialEnd) : null,
            'cancel_at_period_end' => $subscription->cancelAtPeriodEnd,
            'canceled_at' => $subscription->canceledAt !== null ? Carbon::make($subscription->canceledAt) : null,
            'ends_at' => $subscription->endsAt !== null
                ? Carbon::make($subscription->endsAt)
                : ($subscription->endedAt !== null ? Carbon::make($subscription->endedAt) : null),
            'pause_at_period_end' => $subscription->pauseAtPeriodEnd,
            'paused_at' => $subscription->pausedAt !== null ? Carbon::make($subscription->pausedAt) : null,
            'resumes_at' => $subscription->resumesAt !== null ? Carbon::make($subscription->resumesAt) : null,
            'started_at' => $subscription->startedAt !== null ? Carbon::make($subscription->startedAt) : null,
            'past_due_at' => $subscription->pastDueAt !== null ? Carbon::make($subscription->pastDueAt) : null,
        ];

        if ($subscription->modifiedAt !== null) {
            $values['polar_updated_at_us'] = $this->watermarkMicros($subscription->modifiedAt);
        }

        $this->update($values);

        return $this;
    }

    /**
     * Sync the subscription with the given attributes.
     *
     * `$eventTimestamp` is the webhook envelope timestamp. Polar only sets `modified_at` on
     * updates, so it is null on `*.created` events; the envelope timestamp keeps those events
     * ordered against the updates that follow them.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function sync(array $attributes, ?\DateTimeInterface $eventTimestamp = null): self
    {
        $modifiedAt = isset($attributes['modified_at']) ? Carbon::make($attributes['modified_at']) : null;
        $watermark = $this->orderingWatermark($modifiedAt, $eventTimestamp);

        if ($this->isStale($watermark)) {
            return $this;
        }

        $values = [
            'status' => \is_string($attributes['status']) ? SubscriptionStatus::from($attributes['status']) : $attributes['status'],
            'product_id' => $attributes['product_id'],
            'discount_id' => $attributes['discount_id'] ?? null,
            'current_period_start' => isset($attributes['current_period_start']) ? Carbon::make($attributes['current_period_start']) : null,
            'current_period_end' => isset($attributes['current_period_end']) ? Carbon::make($attributes['current_period_end']) : null,
            'trial_ends_at' => isset($attributes['trial_end']) ? Carbon::make($attributes['trial_end']) : null,
            'cancel_at_period_end' => (bool) ($attributes['cancel_at_period_end'] ?? false),
            'canceled_at' => isset($attributes['canceled_at']) ? Carbon::make($attributes['canceled_at']) : null,
            'ends_at' => isset($attributes['ends_at']) ? Carbon::make($attributes['ends_at']) : null,
            'pause_at_period_end' => (bool) ($attributes['pause_at_period_end'] ?? false),
            'paused_at' => isset($attributes['paused_at']) ? Carbon::make($attributes['paused_at']) : null,
            'resumes_at' => isset($attributes['resumes_at']) ? Carbon::make($attributes['resumes_at']) : null,
            'started_at' => isset($attributes['started_at']) ? Carbon::make($attributes['started_at']) : null,
            'past_due_at' => isset($attributes['past_due_at']) ? Carbon::make($attributes['past_due_at']) : null,
        ];

        if ($watermark !== null) {
            $values['polar_updated_at_us'] = $watermark;
        }

        $this->update($values);

        return $this;
    }

    /**
     * Pick the newest timestamp we can trust for ordering this delivery.
     *
     * Both timestamps come from Polar's clock: `modified_at` is the resource update time and
     * the envelope timestamp is when the event was emitted. Taking the later of the two keeps
     * a newer update from looking stale just because one of the fields is missing. The result
     * is stored as UTC microseconds so it survives timezone and sub-second resolution.
     */
    private function orderingWatermark(?\DateTimeInterface $modifiedAt, ?\DateTimeInterface $eventTimestamp): ?int
    {
        $modifiedMicros = $this->watermarkMicros($modifiedAt);
        $eventMicros = $this->watermarkMicros($eventTimestamp);

        if ($modifiedMicros === null) {
            return $eventMicros;
        }

        return $eventMicros !== null && $eventMicros > $modifiedMicros ? $eventMicros : $modifiedMicros;
    }

    /**
     * Convert an instant to UTC microseconds since the epoch.
     */
    private function watermarkMicros(?\DateTimeInterface $timestamp): ?int
    {
        return $timestamp === null ? null : (int) Carbon::make($timestamp)->getPreciseTimestamp(6);
    }

    /**
     * Determine whether an incoming webhook describes an older state than the one we hold.
     *
     * Polar does not guarantee webhook delivery order, so a delayed event must not roll the
     * local subscription back to a state the customer has already left.
     */
    public function isStale(?int $incomingWatermarkUs): bool
    {
        return $incomingWatermarkUs !== null
            && $this->polar_updated_at_us !== null
            && $incomingWatermarkUs < $this->polar_updated_at_us;
    }


    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
            'ends_at' => 'datetime',
            'pause_at_period_end' => 'boolean',
            'paused_at' => 'datetime',
            'resumes_at' => 'datetime',
            'started_at' => 'datetime',
            'past_due_at' => 'datetime',
            'polar_updated_at_us' => 'integer',
        ];
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }
}

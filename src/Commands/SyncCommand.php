<?php

namespace Danestves\LaravelPolar\Commands;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Order;
use Danestves\LaravelPolar\Subscription;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Backfill local orders and subscriptions from the Polar API.
 *
 * Useful when installing the package against an existing Polar account, or after a webhook
 * outage: the same sync methods the webhook handlers use are applied to every remote row.
 */
class SyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polar:sync
                            {--resource=all : What to sync: all, orders, or subscriptions}
                            {--limit=100 : Records per page (capped at 100)}
                            {--dry-run : Report what would be synced without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill local Polar orders and subscriptions from the Polar API';

    public function handle(): int
    {
        $resource = (string) $this->option('resource');
        $limit = min(100, max(1, (int) $this->option('limit')));
        $dryRun = (bool) $this->option('dry-run');

        if (! \in_array($resource, ['all', 'orders', 'subscriptions'], true)) {
            $this->error("Invalid resource [{$resource}]. Use one of: all, orders, subscriptions.");

            return static::FAILURE;
        }

        if (! is_string(config('polar.access_token')) || config('polar.access_token') === '') {
            $this->error('Polar access token not set. Add POLAR_ACCESS_TOKEN to your environment first.');

            return static::FAILURE;
        }

        try {
            if ($resource === 'all' || $resource === 'orders') {
                $this->syncOrders($limit, $dryRun);
            }

            if ($resource === 'all' || $resource === 'subscriptions') {
                $this->syncSubscriptions($limit, $dryRun);
            }
        } catch (\Throwable $exception) {
            $this->error("Failed to sync from Polar: {$exception->getMessage()}");

            return static::FAILURE;
        }

        return static::SUCCESS;
    }

    private function syncOrders(int $limit, bool $dryRun): void
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $page = 1;

        do {
            $response = LaravelPolar::client()->page('/v1/orders/', Data\Order::class, [
                'limit' => $limit,
                'page' => $page,
            ]);

            if (\count($response) === 0) {
                break;
            }

            foreach ($response as $order) {
                $billable = $this->resolveBillable($order->customer->metadata);

                if ($billable === null) {
                    $this->warn("Skipping order {$order->id}: could not resolve a local billable from the customer metadata.");
                    $counts['skipped']++;

                    continue;
                }

                $exists = Order::query()->where('polar_id', $order->id)->exists();

                if ($dryRun) {
                    $counts[$exists ? 'updated' : 'created']++;

                    continue;
                }

                $local = Order::query()->firstOrNew(['polar_id' => $order->id]);

                if (! $local->exists) {
                    $local->fill($this->orderSeed($order, $billable));
                }

                $local->fill([
                    'billable_id' => $billable->getKey(),
                    'billable_type' => $billable->getMorphClass(),
                ]);

                $local->save();
                $local->sync($this->orderAttributes($order));

                $counts[$exists ? 'updated' : 'created']++;
            }

            $page++;
        } while ($page <= $response->pagination->maxPage);

        $this->report('orders', $counts, $dryRun);
    }

    private function syncSubscriptions(int $limit, bool $dryRun): void
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $page = 1;

        do {
            $response = LaravelPolar::client()->page('/v1/subscriptions/', Data\Subscription::class, [
                'limit' => $limit,
                'page' => $page,
            ]);

            if (\count($response) === 0) {
                break;
            }

            foreach ($response as $subscription) {
                $metadata = $subscription->customer->metadata;
                $billable = $this->resolveBillable($metadata);

                if ($billable === null) {
                    $this->warn("Skipping subscription {$subscription->id}: could not resolve a local billable from the customer metadata.");
                    $counts['skipped']++;

                    continue;
                }

                $exists = Subscription::query()->where('polar_id', $subscription->id)->exists();

                if ($dryRun) {
                    $counts[$exists ? 'updated' : 'created']++;

                    continue;
                }

                $type = isset($metadata['subscription_type']) && is_string($metadata['subscription_type'])
                    ? $metadata['subscription_type']
                    : 'default';

                $local = Subscription::query()->firstOrNew(['polar_id' => $subscription->id]);

                if (! $local->exists) {
                    $local->fill($this->subscriptionSeed($subscription, $billable, $type));
                }

                $local->fill([
                    'billable_id' => $billable->getKey(),
                    'billable_type' => $billable->getMorphClass(),
                ]);

                $local->save();
                $local->sync($this->subscriptionAttributes($subscription));

                $counts[$exists ? 'updated' : 'created']++;
            }

            $page++;
        } while ($page <= $response->pagination->maxPage);

        $this->report('subscriptions', $counts, $dryRun);
    }

    /**
     * Resolve the local billable model from Polar's customer metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function resolveBillable(array $metadata): ?Model
    {
        $id = $metadata['billable_id'] ?? null;
        $type = $metadata['billable_type'] ?? null;

        if ($id === null || ! is_string($type) || $type === '') {
            return null;
        }

        $class = Relation::getMorphedModel($type) ?? (class_exists($type) ? $type : null);

        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        $model = $class::query()->find($id);

        return $model instanceof Model ? $model : null;
    }

    /**
     * Required attributes for a brand-new order row, before {@see Order::sync()}.
     *
     * @return array<string, mixed>
     */
    private function orderSeed(Data\Order $order, Model $billable): array
    {
        return [
            'billable_id' => $billable->getKey(),
            'billable_type' => $billable->getMorphClass(),
            'status' => $order->status,
            'amount' => $order->netAmount,
            'tax_amount' => $order->taxAmount,
            'refunded_amount' => $order->refundedAmount,
            'refunded_tax_amount' => $order->refundedTaxAmount,
            'currency' => $order->currency,
            'billing_reason' => $order->billingReason->value,
            'customer_id' => $order->customerId,
            'product_id' => $order->productId,
            'ordered_at' => $order->createdAt,
        ];
    }

    /**
     * The snake_case shape {@see Order::sync()} expects.
     *
     * @return array<string, mixed>
     */
    private function orderAttributes(Data\Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status->value,
            'net_amount' => $order->netAmount,
            'tax_amount' => $order->taxAmount,
            'refunded_amount' => $order->refundedAmount,
            'refunded_tax_amount' => $order->refundedTaxAmount,
            'currency' => $order->currency,
            'billing_reason' => $order->billingReason->value,
            'customer_id' => $order->customerId,
            'product_id' => $order->productId,
            'created_at' => $order->createdAt,
            'modified_at' => $order->modifiedAt,
        ];
    }

    /**
     * Required attributes for a brand-new subscription row, before {@see Subscription::sync()}.
     *
     * @return array<string, mixed>
     */
    private function subscriptionSeed(Data\Subscription $subscription, Model $billable, string $type): array
    {
        return [
            'billable_id' => $billable->getKey(),
            'billable_type' => $billable->getMorphClass(),
            'type' => $type,
            'status' => $subscription->status,
            'product_id' => $subscription->productId,
            'discount_id' => $subscription->discountId,
            'current_period_start' => $subscription->currentPeriodStart,
            'current_period_end' => $subscription->currentPeriodEnd,
            'trial_ends_at' => $subscription->trialEnd,
            'cancel_at_period_end' => $subscription->cancelAtPeriodEnd,
            'canceled_at' => $subscription->canceledAt,
            'ends_at' => $subscription->endsAt ?? $subscription->endedAt,
            'pause_at_period_end' => $subscription->pauseAtPeriodEnd,
            'paused_at' => $subscription->pausedAt,
            'resumes_at' => $subscription->resumesAt,
            'started_at' => $subscription->startedAt,
            'past_due_at' => $subscription->pastDueAt,
            'polar_updated_at_us' => $subscription->modifiedAt !== null
                ? (int) \Carbon\Carbon::make($subscription->modifiedAt)->getPreciseTimestamp(6)
                : null,
        ];
    }

    /**
     * The snake_case shape {@see Subscription::sync()} expects.
     *
     * @return array<string, mixed>
     */
    private function subscriptionAttributes(Data\Subscription $subscription): array
    {
        return [
            'status' => $subscription->status->value,
            'product_id' => $subscription->productId,
            'discount_id' => $subscription->discountId,
            'current_period_start' => $subscription->currentPeriodStart,
            'current_period_end' => $subscription->currentPeriodEnd,
            'trial_end' => $subscription->trialEnd,
            'cancel_at_period_end' => $subscription->cancelAtPeriodEnd,
            'canceled_at' => $subscription->canceledAt,
            'ends_at' => $subscription->endsAt ?? $subscription->endedAt,
            'pause_at_period_end' => $subscription->pauseAtPeriodEnd,
            'paused_at' => $subscription->pausedAt,
            'resumes_at' => $subscription->resumesAt,
            'started_at' => $subscription->startedAt,
            'past_due_at' => $subscription->pastDueAt,
            'modified_at' => $subscription->modifiedAt,
        ];
    }

    /**
     * @param  array{created: int, updated: int, skipped: int}  $counts
     */
    private function report(string $resource, array $counts, bool $dryRun): void
    {
        $prefix = $dryRun ? 'Dry run: ' : '';

        $this->info(sprintf(
            '%s%s — %d created, %d updated, %d skipped.',
            $prefix,
            $resource,
            $counts['created'],
            $counts['updated'],
            $counts['skipped'],
        ));
    }
}

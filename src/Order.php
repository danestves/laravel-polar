<?php

namespace Danestves\LaravelPolar;

use Danestves\LaravelPolar\Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Polar\Models\Components\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $billable_type
 * @property int $billable_id
 * @property string|null $polar_id
 * @property OrderStatus $status
 * @property int $amount
 * @property int $tax_amount
 * @property int $refunded_amount
 * @property int $refunded_tax_amount
 * @property string $currency
 * @property string $billing_reason
 * @property string $customer_id
 * @property string $product_id
 * @property \Carbon\CarbonInterface|null $refunded_at
 * @property \Carbon\CarbonInterface $ordered_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property \Danestves\LaravelPolar\Billable $billable
 *
 * @mixin \Eloquent
 */
class Order extends Model // @phpstan-ignore-line propertyTag.trait - Billable is used in the user final code
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'polar_orders';

    /**
    * The attributes that are not mass assignable.
    *
    * @var array<string>
    */
    protected $guarded = [];

    /**
     * Get the billable model related to the customer.
     *
     * @return MorphTo<Model, covariant $this>
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if the order is paid.
     */
    public function paid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    /**
     * Filter query by paid.
     *
     * @param  Builder<Order>  $query
     */
    public function scopePaid(Builder $query): void
    {
        $query->where('status', OrderStatus::Paid);
    }

    /**
     * Cached custom-field data fetched from Polar.
     *
     * @var array<string, string|int|bool|\DateTime|null>|null
     */
    protected ?array $cachedCustomFieldData = null;

    /**
     * Fetch the custom-field data captured at checkout for this order.
     *
     * Data is not persisted in `polar_orders`; this method fetches the order
     * from Polar on demand and memoizes the result for the lifetime of this
     * Order instance.
     *
     * @return array<string, string|int|bool|\DateTime|null>
     *
     * @throws \Polar\Models\Errors\APIException
     * @throws \Exception
     */
    public function customFieldData(): array
    {
        if ($this->cachedCustomFieldData !== null) {
            return $this->cachedCustomFieldData;
        }

        if ($this->polar_id === null) {
            return $this->cachedCustomFieldData = [];
        }

        $sdkResponse = LaravelPolar::sdk()->orders->get(id: $this->polar_id);
        $sdkOrder = $sdkResponse->order;

        if ($sdkOrder === null) {
            return $this->cachedCustomFieldData = [];
        }

        return $this->cachedCustomFieldData = $sdkOrder->customFieldData ?? [];
    }

    /**
     * Issue a refund for this order. Defaults to refunding the remaining
     * unrefunded amount with reason "customer_request".
     *
     * @param  array<string, scalar|null>|null  $metadata
     *
     * @throws \Polar\Models\Errors\APIException
     * @throws \Exception
     */
    public function refund(
        ?int $amount = null,
        ?\Polar\Models\Components\RefundReason $reason = null,
        ?string $comment = null,
        ?array $metadata = null,
    ): \Polar\Models\Components\Refund {
        if ($this->polar_id === null) {
            throw new \RuntimeException('Order has no polar_id; cannot refund.');
        }

        $request = new \Polar\Models\Components\RefundCreate(
            orderId: $this->polar_id,
            reason: $reason ?? \Polar\Models\Components\RefundReason::CustomerRequest,
            amount: $amount ?? max(0, $this->amount - $this->refunded_amount),
            metadata: $metadata,
            comment: $comment,
        );

        return LaravelPolar::createRefund($request);
    }

    /**
     * List refunds for this order.
     *
     * @return \Illuminate\Support\Collection<int, \Polar\Models\Components\Refund>
     *
     * @throws \Polar\Models\Errors\APIException
     * @throws \Exception
     */
    public function refunds(): \Illuminate\Support\Collection
    {
        if ($this->polar_id === null) {
            return collect();
        }

        $response = LaravelPolar::listRefunds(
            new \Polar\Models\Operations\RefundsListRequest(orderId: $this->polar_id),
        );

        return collect($response->listResourceRefund->items ?? []);
    }

    /**
     * Check if the order is refunded.
     */
    public function refunded(): bool
    {
        return $this->status === OrderStatus::Refunded;
    }

    /**
     * Filter query by refunded.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeRefunded(Builder $query): void
    {
        $query->where('status', OrderStatus::Refunded);
    }

    /**
     * Check if the order is partially refunded.
     */
    public function partiallyRefunded(): bool
    {
        return $this->status === OrderStatus::PartiallyRefunded;
    }

    /**
     * Filter query by partially refunded.
     *
     * @param  Builder<Order>  $query
     */
    public function scopePartiallyRefunded(Builder $query): void
    {
        $query->where('status', OrderStatus::PartiallyRefunded);
    }

    /**
     * Check if the order is void.
     */
    public function void(): bool
    {
        return $this->status === OrderStatus::Void;
    }

    /**
     * Filter query by void.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeVoid(Builder $query): void
    {
        $query->where('status', OrderStatus::Void);
    }

    /**
     * Determine if the order is for a specific product.
     */
    public function hasProduct(string $productId): bool
    {
        return $this->product_id === $productId;
    }

    /**
     * Sync the order with the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function sync(array $attributes): self
    {
        $this->update([
            'polar_id' => $attributes['id'],
            'status' => \is_string($attributes['status']) ? OrderStatus::from($attributes['status']) : $attributes['status'],
            'amount' => $attributes['amount'],
            'tax_amount' => $attributes['tax_amount'],
            'refunded_amount' => $attributes['refunded_amount'],
            'refunded_tax_amount' => $attributes['refunded_tax_amount'],
            'currency' => $attributes['currency'],
            'billing_reason' => $attributes['billing_reason'],
            'customer_id' => $attributes['customer_id'],
            'product_id' => $attributes['product_id'],
            'refunded_at' => $attributes['refunded_at'],
            'ordered_at' => $attributes['created_at'],
        ]);

        return $this;
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'ordered_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}

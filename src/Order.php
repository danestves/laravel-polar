<?php

namespace Danestves\LaravelPolar;

use Danestves\LaravelPolar\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string $product_price_id
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
    /** @use HasFactory<\Danestves\LaravelPolar\Database\Factories\OrderFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'polar_orders';

    /**
    * The attributes that are not mass assignable.
    *
    * @var array<string>|bool
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
     * Determine if the order is for a specific product.
     */
    public function hasProduct(string $productId): bool
    {
        return $this->product_id === $productId;
    }

    /**
     * Determine if the order is for a specific price.
     */
    public function hasPrice(string $productPriceId): bool
    {
        return $this->product_price_id === $productPriceId;
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
            'status' => $attributes['status'],
            'amount' => $attributes['amount'],
            'tax_amount' => $attributes['tax_amount'],
            'refunded_amount' => $attributes['refunded_amount'],
            'refunded_tax_amount' => $attributes['refunded_tax_amount'],
            'currency' => $attributes['currency'],
            'billing_reason' => $attributes['billing_reason'],
            'customer_id' => $attributes['customer_id'],
            'product_id' => $attributes['product_id'],
            'product_price_id' => $attributes['product_price_id'],
            'refunded_at' => $attributes['refunded_at'],
            'ordered_at' => $attributes['ordered_at'],
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
}

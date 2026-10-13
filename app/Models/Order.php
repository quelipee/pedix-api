<?php

namespace App\Models;

use App\Enums\OrderModality;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property OrderModality $modality
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property array{postal_code: string, street: string, number: string, complement?: string|null, neighborhood: string, city: string, state: string}|null $delivery_address
 * @property string|null $delivery_fee
 * @property int|null $courier_id
 * @property int|null $cash_register_id
 * @property Carbon|null $dispatched_at
 * @property OrderStatus $status
 * @property Carbon|null $finalized_at
 * @property PaymentMethod|null $payment_method
 * @property string|null $change_for
 * @property Carbon|null $created_at
 * @property-read Collection<int, OrderItem> $items
 * @property-read Courier|null $courier
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = ['modality', 'customer_name', 'customer_phone', 'delivery_address', 'delivery_fee', 'courier_id', 'cash_register_id', 'dispatched_at', 'status', 'finalized_at', 'payment_method', 'change_for', 'product_id', 'product_name', 'unit_price', 'quantity', 'notes', 'selected_options'];

    protected function casts(): array
    {
        return [
            'modality' => OrderModality::class,
            'status' => OrderStatus::class,
            'finalized_at' => 'datetime',
            'payment_method' => PaymentMethod::class,
            'change_for' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'delivery_address' => 'array',
            'dispatched_at' => 'datetime',
        ];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return BelongsTo<Courier, $this> */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function total(): string
    {
        $totalInCents = $this->items->sum(fn (OrderItem $item): int => (int) str_replace('.', '', (string) $item->unit_price) * $item->quantity)
            + (int) str_replace('.', '', (string) ($this->delivery_fee ?? '0.00'));

        return intdiv($totalInCents, 100).'.'.str_pad((string) ($totalInCents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function changeAmount(): ?string
    {
        if ($this->change_for === null || $this->payment_method !== PaymentMethod::Cash) {
            return null;
        }

        $changeForInCents = (int) str_replace('.', '', (string) $this->change_for);
        $totalInCents = (int) str_replace('.', '', $this->total());
        $changeInCents = $changeForInCents - $totalInCents;

        return intdiv($changeInCents, 100).'.'.str_pad((string) ($changeInCents % 100), 2, '0', STR_PAD_LEFT);
    }
}

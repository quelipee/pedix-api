<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $product_id
 * @property string $product_name
 * @property string $unit_price
 * @property int $quantity
 * @property string|null $notes
 * @property array<int, array{id: int, group: string, name: string, price: string}>|null $selected_options
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'product_name', 'unit_price', 'quantity', 'notes', 'selected_options'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'quantity' => 'integer', 'selected_options' => 'array'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

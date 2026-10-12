<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $category
 * @property string|null $description
 * @property array<int, string>|null $ingredients
 * @property string $price
 * @property bool $active
 * @property-read Collection<int, ProductOptionGroup> $optionGroups
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'category', 'description', 'ingredients', 'price', 'active'];

    protected $attributes = ['category' => 'Outros'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'active' => 'boolean', 'ingredients' => 'array'];
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<ProductOptionGroup, $this> */
    public function optionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionGroup::class)->orderBy('sort_order')->orderBy('id');
    }
}

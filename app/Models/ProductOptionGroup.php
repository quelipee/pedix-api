<?php

namespace App\Models;

use Database\Factories\ProductOptionGroupFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $min_selections
 * @property int $max_selections
 * @property int $sort_order
 * @property-read Collection<int, ProductOption> $options
 */
class ProductOptionGroup extends Model
{
    /** @use HasFactory<ProductOptionGroupFactory> */
    use HasFactory;

    protected $fillable = ['name', 'min_selections', 'max_selections', 'sort_order'];

    protected function casts(): array
    {
        return ['min_selections' => 'integer', 'max_selections' => 'integer', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProductOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort_order')->orderBy('id');
    }
}

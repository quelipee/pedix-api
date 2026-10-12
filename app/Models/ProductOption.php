<?php

namespace App\Models;

use Database\Factories\ProductOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOption extends Model
{
    /** @use HasFactory<ProductOptionFactory> */
    use HasFactory;

    protected $fillable = ['name', 'price', 'active', 'sort_order'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<ProductOptionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductOptionGroup::class, 'product_option_group_id');
    }
}

<?php

namespace App\Models;

use Database\Factories\CourierFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $phone
 * @property bool $active
 * @property int $active_deliveries_count
 * @property-read Collection<int, Order> $orders
 */
class Courier extends Model
{
    /** @use HasFactory<CourierFactory> */
    use HasFactory;

    protected $fillable = ['team_id', 'name', 'phone', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}

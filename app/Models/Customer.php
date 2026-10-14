<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Database\Factories\CustomerFactory;
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
 * @property string|null $phone_normalized
 * @property array{postal_code: string, street: string, number: string, complement?: string|null, neighborhood: string, city: string, state: string}|null $address
 * @property-read Team $team
 * @property-read Collection<int, Order> $orders
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = ['team_id', 'name', 'phone', 'phone_normalized', 'address'];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            if ($customer->isDirty('phone') || $customer->phone_normalized === null) {
                $customer->phone_normalized = PhoneNumber::normalize($customer->phone);
            }
        });
    }

    protected function casts(): array
    {
        return ['address' => 'array'];
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

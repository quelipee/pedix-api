<?php

namespace App\Models;

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cash_register_id
 * @property int $user_id
 * @property CashMovementType $type
 * @property PaymentMethod $payment_method
 * @property string $amount
 * @property string $description
 * @property Carbon|null $created_at
 */
class CashMovement extends Model
{
    protected $fillable = ['cash_register_id', 'user_id', 'type', 'payment_method', 'amount', 'description'];

    protected function casts(): array
    {
        return ['type' => CashMovementType::class, 'payment_method' => PaymentMethod::class, 'amount' => 'decimal:2'];
    }

    /** @return BelongsTo<CashRegister, $this> */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

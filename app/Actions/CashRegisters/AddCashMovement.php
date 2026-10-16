<?php

namespace App\Actions\CashRegisters;

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Models\CashMovement;
use App\Models\CashRegister;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddCashMovement
{
    public function handle(CashRegister $cashRegister, int $teamId, int $userId, CashMovementType $type, string $amount, string $description, PaymentMethod $paymentMethod = PaymentMethod::Cash): CashMovement
    {
        if ($paymentMethod !== PaymentMethod::Cash && ($type !== CashMovementType::Withdrawal || $paymentMethod !== PaymentMethod::Pix)) {
            throw ValidationException::withMessages(['payment_method' => 'Selecione dinheiro ou Pix para a sangria. Suprimentos são registrados em dinheiro.']);
        }

        return DB::transaction(function () use ($cashRegister, $teamId, $userId, $type, $amount, $description, $paymentMethod): CashMovement {
            $lockedRegister = CashRegister::query()->lockForUpdate()->findOrFail($cashRegister->id);

            if ($lockedRegister->team_id !== $teamId || $lockedRegister->closed_at !== null) {
                throw ValidationException::withMessages(['cash_register' => 'O caixa informado não está aberto.']);
            }

            return $lockedRegister->movements()->create([
                'user_id' => $userId,
                'type' => $type,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'description' => $description,
            ]);
        });
    }
}

<?php

namespace App\Actions\CashRegisters;

use App\Enums\CashMovementType;
use App\Models\CashMovement;
use App\Models\CashRegister;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddCashMovement
{
    public function handle(CashRegister $cashRegister, int $teamId, int $userId, CashMovementType $type, string $amount, string $description): CashMovement
    {
        return DB::transaction(function () use ($cashRegister, $teamId, $userId, $type, $amount, $description): CashMovement {
            $lockedRegister = CashRegister::query()->lockForUpdate()->findOrFail($cashRegister->id);

            if ($lockedRegister->team_id !== $teamId || $lockedRegister->closed_at !== null) {
                throw ValidationException::withMessages(['cash_register' => 'O caixa informado não está aberto.']);
            }

            return $lockedRegister->movements()->create([
                'user_id' => $userId,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
            ]);
        });
    }
}

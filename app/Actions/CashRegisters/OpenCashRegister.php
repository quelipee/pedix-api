<?php

namespace App\Actions\CashRegisters;

use App\Models\CashRegister;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenCashRegister
{
    public function handle(int $teamId, int $userId, string $openingBalance): CashRegister
    {
        return DB::transaction(function () use ($teamId, $userId, $openingBalance): CashRegister {
            $alreadyOpen = CashRegister::query()
                ->where('team_id', $teamId)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->exists();

            if ($alreadyOpen) {
                throw ValidationException::withMessages(['cash_register' => 'Já existe um caixa aberto.']);
            }

            return CashRegister::query()->create([
                'team_id' => $teamId,
                'opened_by' => $userId,
                'opening_balance' => $openingBalance,
                'opened_at' => now(),
            ]);
        });
    }
}

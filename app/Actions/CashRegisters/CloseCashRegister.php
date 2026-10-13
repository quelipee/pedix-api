<?php

namespace App\Actions\CashRegisters;

use App\Contracts\CashRegisterCalculator;
use App\Models\CashRegister;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseCashRegister
{
    public function __construct(private readonly CashRegisterCalculator $calculator) {}

    public function handle(CashRegister $cashRegister, int $teamId, int $userId, string $closingBalance): CashRegister
    {
        return DB::transaction(function () use ($cashRegister, $teamId, $userId, $closingBalance): CashRegister {
            $lockedRegister = CashRegister::query()->lockForUpdate()->findOrFail($cashRegister->id);

            if ($lockedRegister->team_id !== $teamId || $lockedRegister->closed_at !== null) {
                throw ValidationException::withMessages(['cash_register' => 'O caixa informado não está aberto.']);
            }

            $expectedCash = $this->calculator->expectedCash($lockedRegister);

            $lockedRegister->update([
                'closed_by' => $userId,
                'closing_balance' => $closingBalance,
                'expected_cash' => $expectedCash,
                'difference' => $this->calculator->difference($closingBalance, $expectedCash),
                'closed_at' => now(),
            ]);

            return $lockedRegister;
        });
    }
}

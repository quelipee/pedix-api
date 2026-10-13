<?php

namespace App\Http\Controllers\CashRegisters;

use App\Contracts\CashRegisterCalculator;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashRegisterPageController extends Controller
{
    public function __construct(private readonly CashRegisterCalculator $calculator) {}

    public function __invoke(Request $request): Response
    {
        $cashRegister = CashRegister::query()
            ->where('team_id', $request->user()->current_team_id)
            ->whereNull('closed_at')
            ->with(['openedBy', 'movements.user'])
            ->latest('opened_at')
            ->first();

        return Inertia::render('cash-registers/Index', [
            'cashRegister' => $cashRegister ? $this->toArray($cashRegister) : null,
            'recentRegisters' => CashRegister::query()
                ->where('team_id', $request->user()->current_team_id)
                ->whereNotNull('closed_at')
                ->with(['openedBy', 'closedBy', 'movements.user'])
                ->latest('closed_at')
                ->limit(5)
                ->get()
                ->map(fn (CashRegister $register): array => [
                    ...$this->toArray($register),
                    'closedBy' => $register->closedBy?->name,
                    'closedAt' => $register->closed_at?->toISOString(),
                    'closingBalance' => $register->closing_balance,
                    'difference' => $register->difference,
                ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function toArray(CashRegister $cashRegister): array
    {
        $salesTotals = $this->calculator->salesTotals($cashRegister);

        return [
            'id' => $cashRegister->id,
            'openedBy' => $cashRegister->openedBy->name,
            'openedAt' => $cashRegister->opened_at->toISOString(),
            'openingBalance' => $cashRegister->opening_balance,
            'expectedCash' => $cashRegister->expected_cash ?? $this->calculator->expectedCash($cashRegister),
            'totalSales' => $this->calculator->totalSales($cashRegister),
            'sales' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method): array => [
                'method' => $method->value,
                'label' => $method->label(),
                'total' => $salesTotals[$method->value],
            ]),
            'supplies' => $this->calculator->movementTotal($cashRegister, CashMovementType::Supply),
            'withdrawals' => $this->calculator->movementTotal($cashRegister, CashMovementType::Withdrawal),
            'movements' => $cashRegister->movements->sortByDesc('id')->values()->map(fn ($movement): array => [
                'id' => $movement->id,
                'type' => $movement->type->value,
                'typeLabel' => $movement->type->label(),
                'amount' => $movement->amount,
                'description' => $movement->description,
                'userName' => $movement->user->name,
                'createdAt' => $movement->created_at?->toISOString(),
            ]),
        ];
    }
}

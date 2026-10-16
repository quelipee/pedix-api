<?php

namespace App\Http\Controllers\CashRegisters;

use App\Actions\CashRegisters\AddCashMovement;
use App\Actions\CashRegisters\CloseCashRegister;
use App\Actions\CashRegisters\OpenCashRegister;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashRegisters\CloseCashRegisterRequest;
use App\Http\Requests\CashRegisters\OpenCashRegisterRequest;
use App\Http\Requests\CashRegisters\StoreCashMovementRequest;
use App\Models\CashRegister;
use Illuminate\Http\JsonResponse;

class CashRegisterController extends Controller
{
    public function open(OpenCashRegisterRequest $request, OpenCashRegister $action): JsonResponse
    {
        $teamId = $request->user()->current_team_id;
        abort_if($teamId === null, 403);

        $cashRegister = $action->handle($teamId, $request->user()->id, $request->validated('opening_balance'));

        return response()->json([
            'data' => ['id' => $cashRegister->id],
        ], 201);
    }

    public function movement(StoreCashMovementRequest $request, CashRegister $cashRegister, AddCashMovement $action): JsonResponse
    {
        $teamId = $request->user()->current_team_id;
        abort_if($teamId === null, 403);

        $movement = $action->handle(
            $cashRegister,
            $teamId,
            $request->user()->id,
            CashMovementType::from($request->validated('type')),
            $request->validated('amount'),
            $request->validated('description'),
            PaymentMethod::from($request->validated('payment_method', PaymentMethod::Cash->value)),
        );

        return response()->json([
            'data' => ['id' => $movement->id],
        ], 201);
    }

    public function close(CloseCashRegisterRequest $request, CashRegister $cashRegister, CloseCashRegister $action): JsonResponse
    {
        $teamId = $request->user()->current_team_id;
        abort_if($teamId === null, 403);

        $closedCashRegister = $action->handle(
            $cashRegister,
            $teamId,
            $request->user()->id,
            $request->validated('closing_balance'),
        );

        return response()->json([
            'data' => [
                'id' => $closedCashRegister->id,
                'expected_cash' => $closedCashRegister->expected_cash,
                'closing_balance' => $closedCashRegister->closing_balance,
                'difference' => $closedCashRegister->difference,
            ],
        ]);
    }
}

<?php

namespace App\Contracts;

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Models\CashRegister;

interface CashRegisterCalculator
{
    /** @return array<string, string> */
    public function salesTotals(CashRegister $cashRegister): array;

    public function totalSales(CashRegister $cashRegister): string;

    public function movementTotal(CashRegister $cashRegister, CashMovementType $type, ?PaymentMethod $paymentMethod = null): string;

    public function netPix(CashRegister $cashRegister): string;

    public function expectedCash(CashRegister $cashRegister): string;

    public function difference(string $counted, string $expected): string;
}

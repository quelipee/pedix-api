<?php

namespace App\Contracts;

use App\Enums\CashMovementType;
use App\Models\CashRegister;

interface CashRegisterCalculator
{
    /** @return array<string, string> */
    public function salesTotals(CashRegister $cashRegister): array;

    public function totalSales(CashRegister $cashRegister): string;

    public function movementTotal(CashRegister $cashRegister, CashMovementType $type): string;

    public function expectedCash(CashRegister $cashRegister): string;

    public function difference(string $counted, string $expected): string;
}

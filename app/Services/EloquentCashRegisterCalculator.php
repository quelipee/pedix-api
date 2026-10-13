<?php

namespace App\Services;

use App\Contracts\CashRegisterCalculator;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Order;

class EloquentCashRegisterCalculator implements CashRegisterCalculator
{
    public function salesTotals(CashRegister $cashRegister): array
    {
        $totals = collect(PaymentMethod::cases())->mapWithKeys(
            fn (PaymentMethod $method): array => [$method->value => '0.00'],
        );

        $cashRegister->orders()->with('items')->get()->each(function (Order $order) use ($totals): void {
            if ($order->payment_method) {
                $totals[$order->payment_method->value] = $this->addAmounts(
                    $totals[$order->payment_method->value],
                    $order->total(),
                );
            }
        });

        return $totals->all();
    }

    public function movementTotal(CashRegister $cashRegister, CashMovementType $type): string
    {
        $total = $cashRegister->movements()->where('type', $type)->get()
            ->sum(fn (CashMovement $movement): int => $this->toCents($movement->amount));

        return $this->fromCents($total);
    }

    public function totalSales(CashRegister $cashRegister): string
    {
        $total = collect($this->salesTotals($cashRegister))
            ->sum(fn (string $amount): int => $this->toCents($amount));

        return $this->fromCents($total);
    }

    public function expectedCash(CashRegister $cashRegister): string
    {
        $cashSales = $this->salesTotals($cashRegister)[PaymentMethod::Cash->value];

        return $this->fromCents(
            $this->toCents($cashRegister->opening_balance)
            + $this->toCents($cashSales)
            + $this->toCents($this->movementTotal($cashRegister, CashMovementType::Supply))
            - $this->toCents($this->movementTotal($cashRegister, CashMovementType::Withdrawal)),
        );
    }

    public function difference(string $counted, string $expected): string
    {
        return $this->fromCents($this->toCents($counted) - $this->toCents($expected));
    }

    private function addAmounts(string $left, string $right): string
    {
        return $this->fromCents($this->toCents($left) + $this->toCents($right));
    }

    private function toCents(string $amount): int
    {
        $isNegative = str_starts_with($amount, '-');
        $unsignedAmount = ltrim($amount, '+-');
        [$whole, $fraction] = array_pad(explode('.', $unsignedAmount, 2), 2, '');
        $cents = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        return $isNegative ? -$cents : $cents;
    }

    private function fromCents(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $absolute = abs($amount);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}

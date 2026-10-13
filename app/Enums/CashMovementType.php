<?php

namespace App\Enums;

enum CashMovementType: string
{
    case Supply = 'suprimento';
    case Withdrawal = 'sangria';

    public function label(): string
    {
        return match ($this) {
            self::Supply => 'Suprimento',
            self::Withdrawal => 'Sangria',
        };
    }
}

<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'dinheiro';
    case Pix = 'pix';
    case Debit = 'debito';
    case Credit = 'credito';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Dinheiro',
            self::Pix => 'Pix',
            self::Debit => 'Débito',
            self::Credit => 'Crédito',
        };
    }
}

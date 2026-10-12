<?php

namespace App\Enums;

enum OrderModality: string
{
    case DineIn = 'local';
    case Takeaway = 'retirada';
    case Delivery = 'entrega';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Consumo',
            self::Takeaway => 'Retirada',
            self::Delivery => 'Entrega',
        };
    }
}

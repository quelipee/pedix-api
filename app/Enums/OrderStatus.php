<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'novo';
    case InPreparation = 'em_preparo';
    case Ready = 'pronto';
    case Finalized = 'finalizado';
    case Cancelled = 'cancelado';

    public function next(): ?self
    {
        return match ($this) {
            self::New => self::InPreparation,
            self::InPreparation => self::Ready,
            self::Ready => self::Finalized,
            self::Finalized, self::Cancelled => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'Novo',
            self::InPreparation => 'Em preparo',
            self::Ready => 'Pronto',
            self::Finalized => 'Finalizado',
            self::Cancelled => 'Cancelado',
        };
    }
}

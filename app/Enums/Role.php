<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Leader = 'leader';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Leader => 'Líder',
            self::Operator => 'Operador',
        };
    }
}

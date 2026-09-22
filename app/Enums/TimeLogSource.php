<?php

namespace App\Enums;

enum TimeLogSource: string
{
    case Timer = 'timer';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Timer => 'Ponto',
            self::Manual => 'Manual',
        };
    }
}

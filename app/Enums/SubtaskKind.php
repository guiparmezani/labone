<?php

namespace App\Enums;

enum SubtaskKind: string
{
    case Internal = 'internal';
    case ThirdParty = 'third_party';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Interna',
            self::ThirdParty => 'Equipe terceira',
        };
    }
}
